<?php

use App\Livewire\Players\PlayerShow;
use App\Models\LapTime;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

// TEST-01 audit follow-up — PlayerShow previously had only route smoke coverage
// (RoutesTest.php); this covers its own favourite-server/display logic directly.
uses(LazilyRefreshDatabase::class);

it('sorts favourite servers by lap count descending, not by rank', function () {
    $map = Map::factory()->create();
    $frequentServer = Server::factory()->create(['name' => 'Frequent Server']);
    $rareServer = Server::factory()->create(['name' => 'Rare Server']);
    $player = Player::factory()->create();

    // Three attempts on the frequent server, but this player's PB there is a poor rank...
    $rival = Player::factory()->create();
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $frequentServer->id, 'player_id' => $rival->id, 'time' => 10]);
    LapTime::factory()->count(3)->create(['map_id' => $map->id, 'server_id' => $frequentServer->id, 'player_id' => $player->id, 'time' => 90]);
    // ...vs. a single, better-ranked attempt on the rare server.
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $rareServer->id, 'player_id' => $player->id, 'time' => 20]);

    $favServers = Livewire::test(PlayerShow::class, ['playerId' => (string) $player->id])->get('favServers');

    expect($favServers)->toHaveCount(2)
        ->and($favServers[0]['server'])->toBe('Frequent Server')
        ->and($favServers[0]['laps'])->toBe(3)
        ->and($favServers[1]['server'])->toBe('Rare Server')
        ->and($favServers[1]['laps'])->toBe(1);
});

it('falls back to a null bestRank when none of the player\'s per-map bests are on that server', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create(['name' => 'Slower Server']);
    $otherServer = Server::factory()->create();
    $player = Player::factory()->create();

    // Same player, same map, raced on both servers — their per-map PB for `$map` is the faster
    // lap on `$otherServer`, so `$server` (where they only have the slower attempt) should have
    // no per-map best attributed to it at all, not merely a poor rank.
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $player->id, 'time' => 90]);
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $otherServer->id, 'player_id' => $player->id, 'time' => 20]);

    $favServers = Livewire::test(PlayerShow::class, ['playerId' => (string) $player->id])->get('favServers');

    $slowerServerEntry = collect($favServers)->firstWhere('server', 'Slower Server');

    expect($slowerServerEntry)->not->toBeNull()
        ->and($slowerServerEntry['bestRank'])->toBeNull();
});

it('reports the player\'s best rank among maps whose PB was set on that server', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create(['name' => 'Winning Server']);
    $player = Player::factory()->create();

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $player->id, 'time' => 50]);

    $favServers = Livewire::test(PlayerShow::class, ['playerId' => (string) $player->id])->get('favServers');

    expect($favServers[0]['server'])->toBe('Winning Server')
        ->and($favServers[0]['bestRank'])->toBe(1);
});

it('counts distinct servers raced on, not distinct laps', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();
    $player = Player::factory()->create();

    LapTime::factory()->count(4)->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $player->id]);

    $statsCard = Livewire::test(PlayerShow::class, ['playerId' => (string) $player->id])->get('statsCard');

    expect($statsCard['serversPlayed'])->toBe(1);
});

it('re-derives favourite servers when its live-update listener fires', function () {
    $map = Map::factory()->create();
    $existingServer = Server::factory()->create(['name' => 'Existing Server']);
    $newServer = Server::factory()->create(['name' => 'New Server']);
    $player = Player::factory()->create();

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $existingServer->id, 'player_id' => $player->id]);

    $component = Livewire::test(PlayerShow::class, ['playerId' => (string) $player->id]);
    expect($component->get('favServers'))->toHaveCount(1);

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $newServer->id, 'player_id' => $player->id]);
    $component->call('onLapSubmitted');

    expect($component->get('favServers'))->toHaveCount(2);
});
