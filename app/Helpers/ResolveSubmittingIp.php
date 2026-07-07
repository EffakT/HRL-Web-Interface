<?php

namespace App\Helpers;

/**
 * Rewrites a lap-submission request's IP if it's a known internal/NAT address standing in for a
 * real public one (ported from `ApiController.php-legacy`'s hardcoded UniFi-router workaround —
 * see `config/webhook.php`'s `internal_ip_map`). Shared by `AppServiceProvider`'s `webhook`
 * rate limiter (runs as middleware, before the controller) and `LapSubmissionController`, so
 * both agree on the same resolved IP — otherwise the rate limiter's "verified" tier marker,
 * keyed by the controller's rewritten IP, would never match a lookup keyed by the raw one.
 */
class ResolveSubmittingIp
{
    public static function resolve(string $ip): string
    {
        return config('webhook.internal_ip_map')[$ip] ?? $ip;
    }
}
