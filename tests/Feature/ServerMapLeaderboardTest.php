<?php

declare(strict_types=1);

use App\Livewire\Servers\ServerMapLeaderboard;
use App\Models\LapTime;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

it('ranks each player by their best lap on this exact server and map', function () {
    $server = Server::factory()->create();
    $map = Map::factory()->create();
    $otherServer = Server::factory()->create();
    $leader = Player::factory()->create(['name' => 'Leader']);
    $elsewhere = Player::factory()->create(['name' => 'Elsewhere']);

    LapTime::factory()->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $leader->id, 'time' => 50]);
    // A faster lap on a *different* server for the same map must not appear here — this
    // component is server-scoped, not global (that's App\Livewire\Maps\MapLeaderboard).
    LapTime::factory()->create(['server_id' => $otherServer->id, 'map_id' => $map->id, 'player_id' => $elsewhere->id, 'time' => 10]);

    $component = Livewire::test(ServerMapLeaderboard::class, ['serverId' => (string) $server->id, 'mapId' => (string) $map->id]);

    expect($component->get('players'))->toHaveCount(1)
        ->and($component->get('players')[0]['name'])->toBe('Leader');
});

// Roadmap item 16 — the listener is what actually runs when the browser's Echo client receives
// a real Reverb broadcast; exercised directly since there's no running WebSocket server in Pest.
it('re-fetches the leaderboard when its live-update listener fires', function () {
    $server = Server::factory()->create();
    $map = Map::factory()->create();
    $existingPlayer = Player::factory()->create(['name' => 'Existing Leader']);

    LapTime::factory()->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $existingPlayer->id, 'time' => 60]);

    $component = Livewire::test(ServerMapLeaderboard::class, ['serverId' => (string) $server->id, 'mapId' => (string) $map->id]);
    expect($component->get('players'))->toHaveCount(1);

    $newPlayer = Player::factory()->create(['name' => 'New Leader']);
    LapTime::factory()->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $newPlayer->id, 'time' => 55]);

    $component->call('onLapSubmitted');

    expect($component->get('players'))->toHaveCount(2)
        ->and($component->get('players')[0]['name'])->toBe('New Leader');
});
