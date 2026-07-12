<?php

use App\Models\LapTime;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('shows real map labels with global lap counts and best times', function () {
    $map = Map::factory()->create([
        'name' => 'machine-map-name',
        'label' => 'Public Map Label',
    ]);
    $serverA = Server::factory()->create();
    $serverB = Server::factory()->create();
    $player = Player::factory()->create();

    LapTime::factory()->create([
        'server_id' => $serverA->id,
        'map_id' => $map->id,
        'player_id' => $player->id,
        'time' => 65.50,
    ]);
    LapTime::factory()->create([
        'server_id' => $serverB->id,
        'map_id' => $map->id,
        'player_id' => $player->id,
        'time' => 59.90,
    ]);

    $this->get('/maps')
        ->assertSee('Public Map Label')
        ->assertDontSee('machine-map-name')
        ->assertSee('2 LAPS')
        ->assertSee('0:59.90');
});

it('hides maps without laps on active servers because they have no leaderboard', function () {
    $map = Map::factory()->create(['label' => 'Unraced Map']);
    $server = Server::factory()->create();

    LapTime::factory()->create([
        'map_id' => $map->id,
        'server_id' => $server->id,
    ]);
    $server->delete();

    $this->get('/maps')
        ->assertDontSee('Unraced Map');
});
