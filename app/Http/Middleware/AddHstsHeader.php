<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web-group only (see bootstrap/app.php). No `includeSubDomains`/`preload`: REL-01's
 * Reverb websocket isn't TLS-ready yet, and /api/v1/* on this same host must stay
 * reachable over plain HTTP for legacy Halo game-server clients (SEC-02 follow-up).
 */
class AddHstsHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000');
        }

        return $response;
    }
}
