<?php

namespace App\Console\Commands;

use App\Models\Server;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

/**
 * A deliberately independent watchdog for `app:refresh-live-server-info` (roadmap item 19) — see
 * docs/decisions.md's 2026-07-10 incident, where a stuck scheduler mutex lock in `cache_locks`
 * silently blocked every tick of that job for ~23 hours with no error anywhere; restarting
 * `schedule:work` didn't help since the lock lives in the database, not the process. Registered
 * via a real OS crontab entry (see docs/deployment.md), not `Schedule::` — if `schedule:work`
 * or Laravel's scheduler itself is the thing that's stuck or dead, a check that also depends on
 * it running would never fire either.
 *
 * Deliberately alerts only (a log line), not self-healing — this environment's own user doesn't
 * have `supervisorctl` access to restart the scheduler process (confirmed 2026-07-10), and
 * blindly deleting `cache_locks` rows on a schedule is a sharper tool than an automated health
 * check should wield unsupervised.
 */
#[Signature('app:check-scheduler-health')]
#[Description("Alert if app:refresh-live-server-info has gone stale, independent of Laravel's own scheduler")]
class CheckSchedulerHealth extends Command
{
    /** How long queried_at can go without updating before this is a real stall, not just a tick in progress. */
    private const int STALENESS_THRESHOLD_MINUTES = 10;

    public function handle(): int
    {
        $lastQueried = Server::max('queried_at');

        if ($lastQueried === null) {
            // No server has ever been queried yet (fresh install, or before the first tick has
            // run at all) — nothing to alert on.
            return self::SUCCESS;
        }

        // Absolute: Carbon 3 (this app's version, via Laravel 13) made diffInMinutes() signed by
        // default — a past timestamp yields a negative number, which is never ">" the threshold.
        $staleForMinutes = (int) round(now()->diffInMinutes(Date::parse($lastQueried), absolute: true));

        if ($staleForMinutes > self::STALENESS_THRESHOLD_MINUTES) {
            Log::critical(
                "Scheduler health check: app:refresh-live-server-info hasn't updated any server in {$staleForMinutes} minute(s) ".
                '(threshold: '.self::STALENESS_THRESHOLD_MINUTES.') — schedule:work or its scheduler mutex may be stuck.'
            );
            $this->error("STALE: last successful query was {$staleForMinutes} minute(s) ago.");

            return self::FAILURE;
        }

        $this->info("OK: last successful query was {$staleForMinutes} minute(s) ago.");

        return self::SUCCESS;
    }
}
