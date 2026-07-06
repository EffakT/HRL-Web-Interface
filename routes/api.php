<?php

use App\Http\Controllers\Api\V1\LapSubmissionController;
use App\Http\Controllers\Api\V1\LapTimeController;
use App\Http\Controllers\Api\V1\MapLeaderboardController;
use App\Http\Controllers\Api\V1\ServerController;
use Illuminate\Support\Facades\Route;

// Public, read-only, rate-limited (see docs/api.md, docs/security.md) — the `api` middleware
// group (throttle:api + route-model-binding substitution) is applied automatically by
// bootstrap/app.php's withRouting(api: ...). No auth: the whole site is already a fully public
// leaderboard with no login system, so this exposes nothing the website itself doesn't already
// show — auth/token strategy stays an open question for if/when that changes.
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/servers', [ServerController::class, 'index'])->name('servers.index');
    Route::get('/maps/{map}/leaderboard', [MapLeaderboardController::class, 'show'])->name('maps.leaderboard');
    Route::get('/laps/{lapTime}', [LapTimeController::class, 'show'])->name('laps.show');

    // Lap-submission webhook (see docs/database.md's "Webhook → job flow") — a write endpoint
    // called by Halo game servers, not browsers. No auth, matching the old app (see
    // docs/security.md). Its own, more generous rate limiter (see AppServiceProvider) replaces
    // the read API's `throttle:api`, since one busy game server's IP submits far more often
    // than a browsing client would.
    Route::post('/laps', [LapSubmissionController::class, 'store'])
        ->withoutMiddleware('throttle:api')
        ->middleware('throttle:webhook')
        ->name('laps.store');
});
