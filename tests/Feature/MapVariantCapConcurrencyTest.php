<?php

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

// TEST-01 audit follow-up (2026-07-09) — "SQLite cannot provide meaningful evidence for this
// race" (the audit's own words): `ProcessNewLap::resolveMapVariant()`'s cap check is a plain
// count-then-write with no locking, so this needs genuine OS-level concurrency against a real
// MySQL connection, not the sqlite :memory: DB the rest of the suite runs on (phpunit.xml forces
// that globally). A dedicated disposable `redesign_hrl_test` database/user now exists for this
// (added 2026-07-09, creds in the gitignored .env.testing) — this runs against that, not the real
// `redesign_hrl` database, with a clearly-namespaced, single-use throwaway map name, cleaned up
// in a finally block regardless of outcome. Two independent real PHP processes (not a fork
// within this Pest process, which would inherit the sqlite-only connection) each call the real
// `resolveMapVariant()` method via `tests/Concurrency/resolve_map_variant_worker.php`.
uses();

function mysqlConcurrencyTestDatabaseName(): string
{
    $database = env('TEST_DB_DATABASE', 'redesign_hrl_test');

    throw_if(! is_string($database) || $database === '' || $database === 'redesign_hrl', RuntimeException::class, 'Refusing to run MySQL concurrency test without a disposable TEST_DB_DATABASE.');

    return $database;
}

function mysqlDirect(): Connection
{
    config(['database.connections.mysql_direct' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => mysqlConcurrencyTestDatabaseName(),
        'username' => env('TEST_DB_USERNAME'),
        'password' => env('TEST_DB_PASSWORD'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ]]);

    return DB::connection('mysql_direct');
}

/**
 * Seeds a throwaway map at (cap - 1) variants, races two real worker processes against it via
 * `resolve_map_variant_worker.php` (barrier-synchronized, see its docblock), and cleans up
 * regardless of outcome. `$lockMode` selects the worker's `ProcessNewLap` subclass — `'locked'`
 * exercises the real `resolveMapVariant()` lock; `'lockless'` is the negative control's
 * deliberately no-op lock (code review follow-up, 2026-07-09), proving this harness itself would
 * catch a missing lock, not just that the current code happens to pass.
 *
 * @return array{outcomes: array{0: string, 1: string}, finalVariantCount: int, cap: int}
 */
function runConcurrentVariantRace(string $lockMode): array
{
    $db = mysqlDirect();
    $cap = 3; // matches config('webhook.max_map_variants_per_name') default; asserted by callers too.
    $mapName = '__concurrency_test_'.bin2hex(random_bytes(6));

    try {
        $db->table('maps')->insert([
            'name' => $mapName,
            'label' => 'Concurrency Test Map',
            'checkpoint_count' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pre-create (cap - 1) existing variants so the map sits exactly one below its cap —
        // the boundary where the race actually matters. Two concurrent requests proposing two
        // *different* new checkpoint counts each try to add one more variant; at most one may
        // succeed if the cap is genuinely enforced under concurrency.
        for ($i = 0; $i < $cap - 1; $i++) {
            $db->table('maps')->insert([
                'name' => "{$mapName}-splits-".(100 + $i),
                'label' => 'Concurrency Test Map Variant',
                'checkpoint_count' => 100 + $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $outputA = tempnam(sys_get_temp_dir(), 'variant_a_');
        $outputB = tempnam(sys_get_temp_dir(), 'variant_b_');
        // Barrier files, not created yet — see the worker script's docblock for why proc_open()
        // alone doesn't guarantee real overlap.
        $readyA = sys_get_temp_dir().'/variant_ready_a_'.bin2hex(random_bytes(6));
        $readyB = sys_get_temp_dir().'/variant_ready_b_'.bin2hex(random_bytes(6));
        $script = __DIR__.'/../Concurrency/resolve_map_variant_worker.php';

        // proc_open() inherits the parent process's environment by default — including
        // phpunit.xml's DB_CONNECTION=sqlite/DB_DATABASE=:memory: overrides, which this whole
        // test exists to route around. Without this override the child processes silently ran
        // against sqlite `:memory:` (a fresh, table-less DB per child), not the real MySQL
        // connection the race actually depends on.
        $childEnv = array_merge(getenv(), [
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => mysqlConcurrencyTestDatabaseName(),
            'DB_USERNAME' => env('TEST_DB_USERNAME'),
            'DB_PASSWORD' => env('TEST_DB_PASSWORD'),
        ]);

        // Each worker waits on the other's ready file (barrier, see worker docblock) before
        // entering the critical section, so both actually overlap regardless of relative
        // app-boot latency. proc_open() returns as soon as each process is spawned, without
        // waiting for it, so both run concurrently from here.
        $procA = proc_open(['php', $script, $mapName, 'Concurrency Test Map', '201', $outputA, $readyA, $readyB, $lockMode], [], $pipesA, null, $childEnv);
        $procB = proc_open(['php', $script, $mapName, 'Concurrency Test Map', '202', $outputB, $readyB, $readyA, $lockMode], [], $pipesB, null, $childEnv);

        proc_close($procA);
        proc_close($procB);

        $resultA = file_get_contents($outputA);
        $resultB = file_get_contents($outputB);
        @unlink($outputA);
        @unlink($outputB);
        @unlink($readyA);
        @unlink($readyB);

        return [
            'outcomes' => [$resultA, $resultB],
            'finalVariantCount' => $db->table('maps')->where('name', 'like', "{$mapName}-splits-%")->count(),
            'cap' => $cap,
        ];
    } finally {
        $db->table('maps')->where('name', $mapName)->orWhere('name', 'like', "{$mapName}-splits-%")->delete();
    }
}

it('never lets concurrent requests push a map past its configured variant cap', function () {
    ['outcomes' => $outcomes, 'finalVariantCount' => $finalVariantCount, 'cap' => $cap] = runConcurrentVariantRace('locked');

    expect(config('webhook.max_map_variants_per_name'))->toBe($cap);

    // Both requests proposed genuinely distinct checkpoint counts (201 vs 202), and the barrier
    // guarantees they actually raced — so, with the lock genuinely held, exactly one must
    // succeed and one must be rejected, and the cap lands exactly at its configured value (not
    // just "at or under" it — a regression that wrongly rejects both would previously have
    // still passed a `<= 1` successes / `<=` cap assertion).
    $successes = count(array_filter($outcomes, fn (string $r) => str_starts_with($r, 'OK:')));
    $rejections = count(array_filter($outcomes, fn (string $r) => $r === 'REJECTED'));

    expect($outcomes)->each(fn ($outcome) => $outcome->not->toStartWith('ERROR:'))
        ->and($successes)->toBe(1)
        ->and($rejections)->toBe(1)
        ->and($finalVariantCount)->toBe($cap);
})->skip(
    fn () => blank(env('TEST_DB_USERNAME')) || blank(env('TEST_DB_PASSWORD')),
    'Needs a dedicated MySQL test database — set TEST_DB_DATABASE/TEST_DB_USERNAME/TEST_DB_PASSWORD in .env.testing (see docs/testing.md\'s "Dedicated test database" section). Not part of a fresh checkout by design — this test exercises a real MySQL row lock SQLite cannot.',
);

it('proves the harness itself would catch a missing lock (negative control, code review follow-up 2026-07-09)', function () {
    ['outcomes' => $outcomes, 'finalVariantCount' => $finalVariantCount, 'cap' => $cap] = runConcurrentVariantRace('lockless');

    // With the lock deliberately a no-op, both workers read the same (cap - 1) under-cap count
    // during the 300ms delay window and both insert — deterministic given that fixed delay, not
    // a flaky "might race" outcome. If this test ever started passing with `$successes === 1`
    // instead, the harness itself (not production code) would have silently lost its ability to
    // detect a real regression — exactly the gap the previous, unsynchronized version of this
    // test file had.
    $successes = count(array_filter($outcomes, fn (string $r) => str_starts_with($r, 'OK:')));

    expect($outcomes)->each(fn ($outcome) => $outcome->not->toStartWith('ERROR:'))
        ->and($successes)->toBe(2)
        ->and($finalVariantCount)->toBe($cap + 1);
})->skip(
    fn () => blank(env('TEST_DB_USERNAME')) || blank(env('TEST_DB_PASSWORD')),
    'Needs a dedicated MySQL test database — set TEST_DB_DATABASE/TEST_DB_USERNAME/TEST_DB_PASSWORD in .env.testing (see docs/testing.md\'s "Dedicated test database" section). Not part of a fresh checkout by design — this test exercises a real MySQL row lock SQLite cannot.',
);
