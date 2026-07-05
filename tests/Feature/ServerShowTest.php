<?php

use App\Livewire\Servers\ServerShow;
use App\Models\LapTime;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

it('excludes archived-server laps from global record references', function () {
    $map = Map::factory()->create();
    $activeServer = Server::factory()->create();
    $archivedServer = Server::factory()->create();
    $activePlayer = Player::factory()->create(['name' => 'Active Record Holder']);
    $archivedPlayer = Player::factory()->create(['name' => 'Archived Record Holder']);

    $activeLap = LapTime::factory()->create([
        'map_id' => $map->id,
        'server_id' => $activeServer->id,
        'player_id' => $activePlayer->id,
        'time' => 65,
    ]);
    LapTime::factory()->create([
        'map_id' => $map->id,
        'server_id' => $archivedServer->id,
        'player_id' => $archivedPlayer->id,
        'time' => 60,
    ]);
    $archivedServer->delete();

    $laps = Livewire::test(ServerShow::class, ['serverId' => (string) $activeServer->id])->get('latestLaps');

    expect($laps)->toHaveCount(1)
        ->and($laps[0]['recordLapId'])->toBe($activeLap->id)
        ->and($laps[0]['recordHolder'])->toBe('Active Record Holder')
        ->and($laps[0]['recordTime'])->toBe('1:05.00');
});
