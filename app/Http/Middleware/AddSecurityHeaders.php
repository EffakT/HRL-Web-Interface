<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEC-05 audit follow-up (docs/security.md) — response hardening headers were largely absent
 * (no CSP, no X-Content-Type-Options, no clickjacking protection, no Referrer-Policy, no
 * Permissions-Policy). Registered on both the `web` and `api` groups (see bootstrap/app.php) —
 * every header here is a no-op for a JSON API response (CSP/frame-ancestors only affect how a
 * *document* renders, not a raw fetched response), so there's no reason to scope this to `web`
 * only.
 *
 * The CSP now ships enforced (`Content-Security-Policy`), not report-only — validated first by
 * inspecting the real rendered homepage (no inline `<script>`, no `style=` attributes, no
 * `eval`/`new Function` in the built JS, every asset same-origin, no external CDN dependencies
 * at all) and then, before flipping from report-only, by driving a real external Playwright
 * browser against the real live domain across every page type (home, servers/maps/players
 * lists, server show, nested server-map-leaderboard, server-scoped player show, map
 * leaderboard, player show) and every real interaction (mobile nav menu, podium/table
 * lap-detail modals, pagination) plus a real Echo/Reverb-delivered live update (a genuine
 * disposable lap broadcast, confirmed to update the page reactively via Livewire) — zero CSP
 * violations, zero JS errors, on all of it.
 */
class AddSecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Suppresses the exact PHP version this server discloses via `X-Powered-By` (SEC-05).
        // `expose_php` itself is a php.ini/php-fpm-pool setting this environment has no access
        // to (same FastPanel-managed-config limitation as elsewhere in this app) — header_remove()
        // works instead because PHP only queues the header internally until the response is
        // actually flushed, which hasn't happened yet at this point in the middleware stack.
        header_remove('X-Powered-By');

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Explicit opt-out of every browser feature this app has no use for, rather than an
        // empty/absent header — a locked-down default that only needs loosening if a real
        // feature (e.g. clipboard access for a "copy link" button) is added later.
        $response->headers->set('Permissions-Policy', implode(', ', [
            'accelerometer=()', 'camera=()', 'geolocation=()', 'gyroscope=()',
            'magnetometer=()', 'microphone=()', 'payment=()', 'usb=()',
        ]));

        $httpOrigin = config('app.url');
        $wsOrigin = preg_replace('/^http/', 'ws', (string) $httpOrigin);

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            // 'unsafe-inline' is for Livewire's own injected <style> block (wire:loading/
            // x-cloak rules), not application code — see class docblock.
            "style-src 'self' 'unsafe-inline'",
            "font-src 'self'",
            "img-src 'self' data:",
            "connect-src 'self' {$httpOrigin} {$wsOrigin}",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]));

        return $response;
    }
}
