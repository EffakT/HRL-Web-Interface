<?php

use App\Exceptions\TooManyMapVariantsException;
use App\Jobs\ProcessNewLap;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Sleep;

/**
 * TEST-01 audit follow-up (2026-07-09) — a standalone worker process (not a Pest test file, not
 * autoloaded by any testsuite) that boots the real app against the real MySQL connection
 * (phpunit.xml forces sqlite for every Pest process, which can't provide genuine concurrency
 * evidence for a real DB race) and calls `ProcessNewLap::resolveMapVariant()` directly via
 * reflection, in isolation from the rest of the webhook pipeline (validation, HRL verification,
 * idempotency) which is irrelevant to the specific race this exercises.
 *
 * Usage: php resolve_map_variant_worker.php <mapName> <mapLabel> <checkpointCount> <outputFile>
 *        <ownReadyFile> <otherReadyFile> <lockMode: locked|lockless>
 *
 * The last two args are a real synchronization barrier (code review follow-up, 2026-07-09):
 * `proc_open()` alone only guarantees both processes *start*, not that they actually reach
 * `resolveMapVariant()`'s count-then-write critical section at overlapping times — app-boot
 * time varies per process, so without this, one worker could realistically finish (and commit)
 * entirely before the other even starts, which wouldn't exercise the lock/race at all. Each
 * worker touches its own ready file once booted, then waits (up to 5s) for the other's ready
 * file before proceeding — guaranteeing both enter the critical section back-to-back. If the
 * other worker never shows up within that window, this worker writes `ERROR:barrier_timeout`
 * and exits *without* calling `resolveMapVariant()` at all (code review follow-up, 2026-07-09)
 * — proceeding anyway would let the test still pass without ever actually proving the two
 * workers raced, silently degrading back into the exact non-overlap gap the barrier exists to
 * close.
 *
 * A second, subtler gap (code review follow-up, 2026-07-09): the ready-file barrier only proves
 * both workers reach `resolveMapVariant()` at roughly the same time — after that, MySQL/OS
 * scheduling could still let one worker's entire count-then-insert finish before the other even
 * reads its count, which would produce the exact same passing outcome (one OK, one REJECTED,
 * final count === cap) whether or not `lockForUpdate()` is doing anything at all. To force a
 * genuine overlap *inside* the critical section, this worker uses an anonymous subclass that
 * overrides `ProcessNewLap::countExistingMapVariants()` (the count-read step) to sleep 300ms
 * before returning — without a real row lock serializing the two transactions, both workers
 * would read the same under-cap count during that window and both would insert, which the test
 * confirmed by temporarily removing `lockForUpdate()` and watching the assertions fail (both OK,
 * final count > cap) before restoring it.
 *
 * That manual removal was a one-off check, not a permanent regression guard — a real future
 * removal of `lockForUpdate()` from `resolveMapVariant()` itself would only be caught by the
 * *primary* concurrency test, same as any other regression. `<lockMode>` (code review follow-up,
 * 2026-07-09) makes the discriminating check permanent instead: `lockless` swaps in a second
 * subclass override, `acquireMapVariantLock()`, as a no-op — letting a dedicated negative-control
 * test in `MapVariantCapConcurrencyTest.php` prove, in CI, on every run, that this exact
 * worker/delay/barrier harness still detects a missing lock — independent of whether the real
 * lock in `ProcessNewLap` itself currently happens to be intact.
 */

require __DIR__.'/../../vendor/autoload.php';

[$script, $mapName, $mapLabel, $checkpointCount, $outputFile, $ownReadyFile, $otherReadyFile, $lockMode] = $argv;

$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

touch($ownReadyFile);
$deadline = microtime(true) + 5;
while (! file_exists($otherReadyFile)) {
    if (microtime(true) >= $deadline) {
        file_put_contents($outputFile, 'ERROR:barrier_timeout');
        exit(0);
    }
    Sleep::usleep(1_000);
}

$job = match ($lockMode) {
    'locked' => new class('127.0.0.1', 2302, ['map_name' => $mapName]) extends ProcessNewLap
    {
        protected function countExistingMapVariants(string $namePattern): int
        {
            $count = parent::countExistingMapVariants($namePattern);
            Sleep::usleep(300_000);

            return $count;
        }
    },
    'lockless' => new class('127.0.0.1', 2302, ['map_name' => $mapName]) extends ProcessNewLap
    {
        protected function countExistingMapVariants(string $namePattern): int
        {
            $count = parent::countExistingMapVariants($namePattern);
            Sleep::usleep(300_000);

            return $count;
        }

        protected function acquireMapVariantLock(string $mapName): void
        {
            // Deliberately no-op — this is the negative control's whole point.
        }
    },
    default => throw new InvalidArgumentException("Unknown lockMode '{$lockMode}', expected 'locked' or 'lockless'."),
};

$method = new ReflectionMethod(ProcessNewLap::class, 'resolveMapVariant');

try {
    $variant = $method->invoke($job, $mapName, $mapLabel, (int) $checkpointCount);
    file_put_contents($outputFile, 'OK:'.$variant->name);
} catch (TooManyMapVariantsException) {
    file_put_contents($outputFile, 'REJECTED');
} catch (Throwable $e) {
    file_put_contents($outputFile, 'ERROR:'.$e::class.':'.$e->getMessage());
}
