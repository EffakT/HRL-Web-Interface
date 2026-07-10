<?php

use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Livewire\Home;
use App\Livewire\Maps\MapLeaderboard;
use App\Livewire\Maps\MapList;
use App\Livewire\Players\PlayerList;
use App\Livewire\Players\PlayerShow;
use App\Livewire\Servers\ServerList;
use App\Livewire\Servers\ServerMapLeaderboard;
use App\Livewire\Servers\ServerPlayerShow;
use App\Livewire\Servers\ServerShow;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt', [RobotsController::class, 'index'])->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::get('/', Home::class)->name('home');

Route::get('/servers', ServerList::class)->name('servers.index');
Route::get('/servers/{serverId}', ServerShow::class)->name('servers.show');
Route::get('/servers/{serverId}/maps/{mapId}', ServerMapLeaderboard::class)->name('servers.maps.show');
Route::get('/servers/{serverId}/players/{playerId}', ServerPlayerShow::class)->name('servers.players.show');

Route::get('/maps', MapList::class)->name('maps.index');
Route::get('/maps/{mapId}', MapLeaderboard::class)->name('maps.show');

Route::get('/players', PlayerList::class)->name('players.index');
Route::get('/players/{playerId}', PlayerShow::class)->name('players.show');

Route::get('/opt-in', fn () => view('opt-in'))->name('opt-in');

Route::get('/contact', fn () => view('contact'))->name('contact');
