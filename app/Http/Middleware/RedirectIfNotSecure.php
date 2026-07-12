<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web-group only (see bootstrap/app.php) — /api/v1/* stays reachable over plain HTTP
 * for legacy Halo game-server clients that can't do TLS (SEC-02 follow-up).
 */
class RedirectIfNotSecure
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->secure() && app()->environment('production', 'staging')) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
