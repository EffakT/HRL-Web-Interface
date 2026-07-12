<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;

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
        $nonce = base64_encode(random_bytes(16));

        Vite::useCspNonce($nonce);

        // Makes the nonce available to Blade and other rendering code.
        app()->instance('csp-nonce', $nonce);

        $response = $next($request);

        header_remove('X-Powered-By');

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set(
            'Referrer-Policy',
            'strict-origin-when-cross-origin'
        );

        $response->headers->set('Permissions-Policy', implode(', ', [
            'accelerometer=()',
            'camera=()',
            'geolocation=()',
            'gyroscope=()',
            'magnetometer=()',
            'microphone=()',
            'payment=()',
            'usb=()',
        ]));

        $httpOrigin = rtrim((string) config('app.url'), '/');
        $wsOrigin = preg_replace('/^http/', 'ws', $httpOrigin);

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
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
