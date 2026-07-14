<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\RedirectIfNotSecure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SEC-02 follow-up (2026-07-08): FastPanel's nginx/PHP-FPM unconditionally sets
        // $_SERVER['HTTPS']="on" and SERVER_PORT=443 for this vhost regardless of the real
        // connection (confirmed empirically — a genuine plain-HTTP request still reported as
        // "secure"), so RedirectIfNotSecure never fired despite the app running in staging.
        // The real topology's outer proxy (Nginx Proxy Manager, see deployment.md) does send an
        // accurate `X-Forwarded-Proto` header — trusting it here from any IP is safe because
        // PHP-FPM only listens on a Unix socket reachable exclusively via this box's own nginx;
        // there's no network path for an external client to reach PHP-FPM directly and spoof it.
        //
        // Deliberately NOT trusting HEADER_X_FORWARDED_HOST/_PORT (2026-07-08 correction): the
        // edge only overwrites forged `X-Forwarded-Proto`/`-For` before they reach this app, not
        // `X-Forwarded-Host`/`-Port` — trusting those turned the redirect above into an open
        // redirect (`X-Forwarded-Host: evil.example` + `X-Forwarded-Port: 444` produced
        // `Location: https://evil.example:444/...`, confirmed live and reported by the user).
        // Only PROTO (needed for the redirect fix) and FOR (this app's real client IP, used by
        // the webhook rate limiter and NAT-remap logic — see ResolveSubmittingIp) are trusted.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_PROTO,
        );

        // Public, read-only API (see docs/api.md) — the whole site is already a fully public
        // leaderboard with no auth, so there's no new data exposure here; rate limiting (not
        // auth) is the actual protection against abuse. Limiter defined in AppServiceProvider.
        $middleware->throttleApi();

        // HTTPS redirect on the `web` group only (SEC-02) — /api/v1/* stays on the `api` group
        // deliberately, since legacy Halo game-server clients call it over plain HTTP and can't
        // do TLS. Deliberately no HSTS (2026-07-08 correction, removing an earlier mistake):
        // HSTS is host-wide, not path-scoped, so advertising it here would tell any HSTS-aware
        // browser to force HTTPS for the *entire* hostname, including `/api/v1/*` — which must
        // stay reachable over plain HTTP for legacy Halo game-server clients that can't do TLS.
        // Reconsider once the legacy API moves to its own hostname or its clients can do TLS.
        $middleware->web(prepend: [
            RedirectIfNotSecure::class,
        ], append: [
            AddSecurityHeaders::class,
        ]);

        // SEC-05: also on `api`, not just `web` — every header AddSecurityHeaders sets is a
        // no-op for a JSON response (CSP/frame-ancestors only affect how a browser renders a
        // *document*), so there's no reason to exempt the read-only API from them.
        $middleware->api(append: [
            AddSecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Laravel's default message leaks the internal Eloquent class name — e.g. "No query
        // results for model [App\Models\Map] bloodgulch2" — which exposes app-internal
        // namespace/class structure to a public API consumer for no benefit. Route-model
        // binding failures (a bad {map}/{lapTime} in the URL) are the real-world case this
        // hits; `getModel()`/`getIds()` already carry everything needed for a clean message.
        //
        // Must be `map()`, not `render()`: the handler's `prepareException()` unconditionally
        // rewrites every `ModelNotFoundException` into a `NotFoundHttpException` (carrying the
        // original message along) BEFORE any registered `render()` callback is even considered
        // — confirmed by reading `Illuminate\Foundation\Exceptions\Handler::render()`, a
        // `render(ModelNotFoundException ...)` callback never actually matches anything at
        // runtime. `map()` runs earlier, so returning an already-`NotFoundHttpException` here
        // (with the clean message) passes through `prepareException()` unchanged, since it's no
        // longer a `ModelNotFoundException` by the time that check runs.
        $exceptions->map(ModelNotFoundException::class, function (ModelNotFoundException $e) {
            // Str::snake(..., ' ') rather than a flat strtolower() — `Map` -> "map" either way,
            // but `LapTime` -> "lap time" instead of the run-together "laptime".
            $model = Str::snake(class_basename($e->getModel()), ' ');
            $ids = implode(', ', $e->getIds());

            return new NotFoundHttpException(trim("No query results for {$model} {$ids}"), $e);
        });
    })->create();
