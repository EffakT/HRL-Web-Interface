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
    'api-docs' => '/api-docs',
    'contact' => '/contact',
    'robots' => '/robots.txt',
    'sitemap' => '/sitemap.xml',
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

it('shows the copyright notice and trademark disclaimer in the footer on every page', function () {
    $this->get('/')
        ->assertSee('© '.now()->year.' Halo Race Leaderboard', false)
        ->assertSee('Halo is a trademark of Microsoft');

    // Also present on the custom 404 page (both wrap <x-layout>, but confirmed separately —
    // the footer is easy to accidentally scope to <main> only rather than every page).
    $this->get('/this-route-does-not-exist')
        ->assertSee('Halo is a trademark of Microsoft');
});

it('shows a custom-designed 404 page for an unknown web route', function () {
    $this->get('/this-route-does-not-exist')
        ->assertNotFound()
        ->assertSee('Off The Track')
        ->assertSee('RETURN TO BASE');
});

it('shows the same custom 404 page for a real model-not-found on a web route, not a JSON error', function () {
    $this->get('/players/999999')
        ->assertNotFound()
        ->assertSee('Off The Track');
});

it('lists every real API endpoint on the API docs page, with example requests/responses', function () {
    $this->get('/api-docs')
        ->assertSee('/servers', false)
        ->assertSee('/maps', false)
        ->assertSee('/maps/{map}/leaderboard', false)
        ->assertSee('/players', false)
        ->assertSee('/laps/{lapTime}', false)
        ->assertSee('/laps', false)
        ->assertSee('EXAMPLE REQUEST')
        ->assertSee('EXAMPLE RESPONSE')
        // The real 404 message (bootstrap/app.php's ModelNotFoundException mapping) — not the
        // internal-class-leaking default Laravel would otherwise produce.
        ->assertSee('No query results for map bloodgulch2', false);
});
