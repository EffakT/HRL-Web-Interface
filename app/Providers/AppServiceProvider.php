<?php

namespace App\Providers;

use App\Helpers\GameServerQuery;
use App\Helpers\QueryServer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GameServerQuery::class, QueryServer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Public read-only API rate limit (see docs/api.md, docs/security.md) — 60/min per IP
        // is a starting point, not a measured/tuned value; revisit if real usage says otherwise.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // The lap-submission webhook (docs/database.md) is machine-to-machine, not a browsing
        // client — a busy server with several racers can legitimately submit far more often
        // than 60/min, so it gets its own, more generous limit rather than sharing the public
        // read API's budget. Keyed by ip+port (the submitted server identity), not IP alone —
        // see docs/security.md's SEC-01 note — so multiple distinct game servers sharing one
        // host's IP don't share a single budget.
        RateLimiter::for('webhook', fn (Request $request) => Limit::perMinute(120)->by($request->ip().':'.$request->input('port')));
    }
}
