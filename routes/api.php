<?php

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
});
