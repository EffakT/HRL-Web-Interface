<?php

namespace App\Listeners;

use App\Events\LapSubmitted;
use App\Livewire\Home;
use Illuminate\Support\Facades\Cache;

/**
 * PERF-01 follow-up (2026-07-08) — see `App\Livewire\Home::CACHE_KEY`'s docblock for why Home's
 * highlights/quick-stats are cached at all. Deliberately not `ShouldQueue`: `ProcessNewLap`
 * (whose `handle()` dispatches `LapSubmitted`) runs *synchronously* inside the webhook's own
 * HTTP request — it deliberately isn't queued, since the submitting game server needs its
 * leaderboard position back in that same response (see `ProcessNewLap`'s docblock) — so this
 * listener already runs inline there too. Queuing it separately would only add latency before
 * invalidation actually happens, for no benefit; bumping a cache generation is cheap enough not
 * to matter for the webhook's own response time.
 */
class InvalidateHomeHighlightsCache
{
    public function handle(LapSubmitted $event): void
    {
        // Bumps a generation counter rather than `Cache::forget(Home::CACHE_KEY)` directly — see
        // `Home::GENERATION_KEY`'s docblock for the real race a plain forget() has. `add()` first
        // (atomic "set if missing", safe under concurrent callers — see `Illuminate\Cache\
        // DatabaseStore::add()`'s `insertOrIgnore`) because `increment()` on the database cache
        // store returns `false` and does nothing if the key has never been set, unlike some
        // other cache drivers which auto-initialize it.
        Cache::add(Home::GENERATION_KEY, 0, null);
        Cache::increment(Home::GENERATION_KEY);
    }
}
