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
 * The CSP ships as `Content-Security-Policy-Report-Only` deliberately, not enforcing —
 * confirmed by inspecting the real rendered homepage first (no inline `<script>`, no `style=`
 * attributes, no `eval`/`new Function` in the built JS, every asset same-origin, no external
 * CDN dependencies at all), but Livewire itself injects a genuine inline `<style>` block into
 * every page (the `[wire\:loading]`/`[x-cloak]` rules) that an enforced `style-src` without
 * `'unsafe-inline'` would need to account for, and this hasn't been exercised against every
 * real interactive page (Livewire component updates, Alpine transitions) yet. Report-only mode
 * can never break page functionality — it only logs violations to the browser console — so this
 * is the safe way to validate the policy against real traffic before flipping to enforced.
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

        $response->headers->set('Content-Security-Policy-Report-Only', implode('; ', [
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
