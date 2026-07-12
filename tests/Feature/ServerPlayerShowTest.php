<?php

use App\Livewire\Servers\ServerPlayerShow;
use App\Models\LapTime;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

// Reached by clicking a player from Server Single's Top Players list — see
// docs/decisions.md and App\Models\PlayerProfile.
uses(LazilyRefreshDatabase::class);

it('404s for a player who has never raced on this server', function () {
    $server = Server::factory()->create();
    $player = Player::factory()->create();

    $this->get(route('servers.players.show', ['serverId' => $server->id, 'playerId' => $player->id]))
        ->assertNotFound();
});

it('shows server rank/score alongside global rank/score', function () {
    $server = Server::factory()->create();
    $otherServer = Server::factory()->create();
    $map = Map::factory()->create();
    $otherMap = Map::factory()->create();
    $leader = Player::factory()->create(['name' => 'Leader']);
    $runnerUp = Player::factory()->create();

    // Leader is #1 on this server (only real competitor here)...
    LapTime::factory()->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $leader->id, 'time' => 50]);
    LapTime::factory()->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $runnerUp->id, 'time' => 55]);
    // ...but #2 globally, since they also race (and lose) on another server/map.
    LapTime::factory()->create(['server_id' => $otherServer->id, 'map_id' => $otherMap->id, 'player_id' => $leader->id, 'time' => 70]);
    LapTime::factory()->create(['server_id' => $otherServer->id, 'map_id' => $otherMap->id, 'player_id' => $runnerUp->id, 'time' => 20]);

    $component = Livewire::test(ServerPlayerShow::class, ['serverId' => (string) $server->id, 'playerId' => (string) $leader->id]);

    expect($component->get('playerInfo')['serverRank'])->toBe(1)
        ->and($component->get('playerInfo')['globalRank'])->toBe(2);
});

it('isolates Performance by Map and Stats to this server only', function () {
    $server = Server::factory()->create();
    $otherServer = Server::factory()->create();
    $mapHere = Map::factory()->create(['label' => 'Raced Here']);
    $mapElsewhere = Map::factory()->create(['label' => 'Raced Elsewhere']);
    $player = Player::factory()->create();

    LapTime::factory()->create(['server_id' => $server->id, 'map_id' => $mapHere->id, 'player_id' => $player->id]);
    LapTime::factory()->create(['server_id' => $otherServer->id, 'map_id' => $mapElsewhere->id, 'player_id' => $player->id]);

    $component = Livewire::test(ServerPlayerShow::class, ['serverId' => (string) $server->id, 'playerId' => (string) $player->id]);

    $maps = collect($component->get('laps'))->pluck('map');

    expect($maps)->toContain('Raced Here')
        ->not->toContain('Raced Elsewhere')
        ->and($component->get('statsCard')['totalValidLaps'])->toBe(1);
});

it('links to the global player profile', function () {
    $server = Server::factory()->create();
    $map = Map::factory()->create();
    $player = Player::factory()->create();
    LapTime::factory()->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $player->id]);

    $this->get(route('servers.players.show', ['serverId' => $server->id, 'playerId' => $player->id]))
        ->assertSee(route('players.show', ['playerId' => $player->id]), escape: false);
});
