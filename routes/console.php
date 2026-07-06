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
Schedule::command('app:refresh-live-server-info')->everyMinute()->withoutOverlapping();
