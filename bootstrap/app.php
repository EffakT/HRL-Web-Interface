<?php

use App\Http\Middleware\AddHstsHeader;
use App\Http\Middleware\RedirectIfNotSecure;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Public, read-only API (see docs/api.md) — the whole site is already a fully public
        // leaderboard with no auth, so there's no new data exposure here; rate limiting (not
        // auth) is the actual protection against abuse. Limiter defined in AppServiceProvider.
        $middleware->throttleApi();

        // HTTPS redirect + HSTS on the `web` group only (SEC-02) — /api/v1/* stays on the
        // `api` group deliberately, since legacy Halo game-server clients call it over plain
        // HTTP and can't do TLS.
        $middleware->web(prepend: [
            RedirectIfNotSecure::class,
        ], append: [
            AddHstsHeader::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
