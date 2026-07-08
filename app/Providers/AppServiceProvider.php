<?php

namespace App\Providers;

use App\Helpers\GameServerQuery;
use App\Helpers\LapSubmissionVerifier;
use App\Helpers\QueryServer;
use App\Helpers\ResolveSubmittingIp;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        // SEC-05 audit follow-up (docs/security.md) — enables Livewire's own official CSP-safe
        // bundle (`livewire.csp.min.js`, ships in the package already, no dependency swap
        // needed), which uses a restricted Alpine build that doesn't need `unsafe-eval` to
        // compile x-data/x-show/x-on directive expressions. A `Content-Security-Policy-Report-
        // Only` scan of the real live homepage (headless Chrome) surfaced exactly one violation
        // type — 'unsafe-eval' required by script-src — traced to Alpine's default eval-based
        // expression evaluator; this is Livewire's own first-party fix for that, not a custom
        // Alpine package swap. Overridden here (config key, not a published config file) since
        // this app doesn't otherwise need its own copy of Livewire's full config.
        config(['livewire.csp_safe' => true]);
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
        // client, but per docs/security.md's SEC-01 audit follow-up it's also the one endpoint
        // an attacker can force expensive UDP verification work on — so it gets its own tiered
        // limiter rather than sharing the public read API's flat budget. A source starts in the
        // strict "unverified" tier and only earns the more generous "verified" tier once a
        // request from that exact ip:port has actually passed HRL query verification (the
        // marker LapSubmissionController sets on success) — verification itself still runs on
        // every request regardless of tier. Three limits apply together within whichever tier
        // applies: a per-second burst allowance, a per-ip:port sustained allowance (the
        // submitted server identity), and a coarser per-IP ceiling that rotating the
        // (unverified, at this layer) `port` value on every request can't evade — that last one
        // applies at both tiers specifically so running many ports can't bypass it even once
        // "verified."
        RateLimiter::for('webhook', function (Request $request) {
            $ip = ResolveSubmittingIp::resolve($request->ip());
            $port = $request->input('port');

            $tier = Cache::has(LapSubmissionVerifier::verifiedMarkerKey($ip, $port))
                ? config('webhook.rate_limit.verified')
                : config('webhook.rate_limit.unverified');

            return [
                Limit::perSecond($tier['burst_per_second'])->by('webhook-burst:'.$ip),
                Limit::perMinute($tier['per_ip_per_minute'])->by('webhook-ip:'.$ip),
                Limit::perMinute($tier['per_ip_port_per_minute'])->by('webhook-ip-port:'.$ip.':'.$port),
            ];
        });
    }
}
