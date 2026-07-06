<?php

use App\Models\LapTime;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

// Automates what had been manual curl+status-code smoke testing throughout the build
// (see docs/testing.md). Most pages still run on mock data, so any id resolves — but
// servers.show, servers.maps.show, maps.show, players.show, and servers.players.show are now
// wired to real Eloquent data (see docs/decisions.md), so they need actual rows with id=1 to
// exist. Replace each remaining hardcoded `1` with a factory row as its page gets wired for
// real, same as these were. servers.players.show additionally 404s for a player with no real
// ranking on that server (see App\Livewire\Servers\ServerPlayerShow), so it needs a real
// LapTime connecting player 1 to server 1 — not just the three rows existing independently.
uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $server = Server::factory()->create(['id' => 1, 'name' => 'Test Server Alpha']);
    $map = Map::factory()->create(['id' => 1, 'label' => 'Test Map Alpha']);
    $player = Player::factory()->create(['id' => 1, 'name' => 'Test Player Alpha']);
    LapTime::factory()->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $player->id]);
});

it('renders every route successfully', function (string $uri) {
    $this->get($uri)->assertSuccessful();
})->with([
    'home' => '/',
    'servers.index' => '/servers',
    'servers.show' => '/servers/1',
    'servers.maps.show (nested leaderboard)' => '/servers/1/maps/1',
    'servers.players.show (server-scoped player profile)' => '/servers/1/players/1',
    'maps.index' => '/maps',
    'maps.show (global leaderboard)' => '/maps/1',
    'players.index' => '/players',
    'players.show' => '/players/1',
    'opt-in' => '/opt-in',
    'contact' => '/contact',
]);

it('shows the server-scoped eyebrow on the nested leaderboard, not the global one', function () {
    $this->get('/servers/1/maps/1')
        ->assertSee('Test Server Alpha')
        ->assertDontSee('ALL SERVERS · GLOBAL');
});

it('shows the global eyebrow on the global leaderboard, not a server name', function () {
    $this->get('/maps/1')
        ->assertSee('ALL SERVERS · GLOBAL');
});
