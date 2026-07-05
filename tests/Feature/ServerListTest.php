<?php

use App\Livewire\Servers\ServerList;
use App\Models\LapTime;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

// ServerList was wired to real data (see docs/decisions.md) — several fields from the original
// mock (region, live online status, player capacity, ping) have no real-schema equivalent and
// were dropped or replaced with an honest derived proxy rather than fabricated. These tests
// assert the real derivation, not just that the route renders (RoutesTest.php covers that).
uses(LazilyRefreshDatabase::class);

it('shows real server names and derives "now playing" from the most recent lap\'s map', function () {
    $server = Server::factory()->create(['name' => 'Real Server Name']);
    $map = Map::factory()->create(['label' => 'Real Map Label']);
    $player = Player::factory()->create();

    LapTime::factory()->create([
        'server_id' => $server->id,
        'map_id' => $map->id,
        'player_id' => $player->id,
        'time' => 65.5,
        'created_at' => now(),
    ]);

    $this->get('/servers')
        ->assertSee('Real Server Name')
        ->assertSee('Real Map Label')
        ->assertSee('1:05.50');
});

it('marks a server online only when it has a lap within the last 24 hours', function () {
    $recent = Server::factory()->create(['name' => 'Recently Active Server']);
    $stale = Server::factory()->create(['name' => 'Stale Server']);
    $map = Map::factory()->create();
    $player = Player::factory()->create();

    LapTime::factory()->create([
        'server_id' => $recent->id,
        'map_id' => $map->id,
        'player_id' => $player->id,
        'created_at' => now()->subHours(2),
    ]);
    LapTime::factory()->create([
        'server_id' => $stale->id,
        'map_id' => $map->id,
        'player_id' => $player->id,
        'created_at' => now()->subDays(10),
    ]);

    $rows = collect(Livewire::test(ServerList::class)->get('servers'));

    expect($rows->firstWhere('name', $recent->name)['online'])->toBeTrue()
        ->and($rows->firstWhere('name', $stale->name)['online'])->toBeFalse();
});

it('computes the total-players header stat as distinct players across all servers, not raw lap count', function () {
    $server = Server::factory()->create();
    $map = Map::factory()->create();
    $playerA = Player::factory()->create();
    $playerB = Player::factory()->create();

    // Player A laps twice — should still count once toward "total players".
    LapTime::factory()->count(2)->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $playerA->id]);
    LapTime::factory()->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $playerB->id]);

    $this->get('/servers')->assertSee('2 PLAYERS');
});
