<?php

use App\Models\Map;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

// Automates what had been manual curl+status-code smoke testing throughout the build
// (see docs/testing.md). Most pages still run on mock data, so any id resolves — but
// servers.show and servers.maps.show are now wired to real Eloquent data (see docs/decisions.md),
// so they need actual Server/Map rows with id=1 to exist. Replace each remaining hardcoded `1`
// with a factory row as its page gets wired for real, same as these were.
uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Server::factory()->create(['id' => 1, 'name' => 'Test Server Alpha']);
    Map::factory()->create(['id' => 1, 'label' => 'Test Map Alpha']);
});

it('renders every route successfully', function (string $uri) {
    $this->get($uri)->assertSuccessful();
})->with([
    'home' => '/',
    'servers.index' => '/servers',
    'servers.show' => '/servers/1',
    'servers.maps.show (nested leaderboard)' => '/servers/1/maps/1',
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
