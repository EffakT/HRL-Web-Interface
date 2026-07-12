<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Roadmap item 19 (docs/database.md) — every minute is a starting point, not a measured/tuned
// value (each query is a ~2s-timeout UDP round trip per server, cheap at this project's real
// scale of a handful of servers); revisit if the server roster grows enough for this to matter.
// Deliberately no ->withoutOverlapping() (2026-07-10 incident, see docs/decisions.md): this job
// is idempotent and a genuinely overlapping run is harmless (each server row just gets updated
// twice in quick succession), but a stuck scheduler mutex lock silently blocked every single
// tick for ~23 hours with no error anywhere — removing the mutex entirely removes that whole
// failure class rather than trying to make the lock more robust.
Schedule::command('app:refresh-live-server-info')->everyMinute();
