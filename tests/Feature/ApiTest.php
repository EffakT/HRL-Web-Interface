<?php

use App\Models\LapTime;
use App\Models\LapTimeSplit;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('lists every active server with real derived stats', function () {
    $server = Server::factory()->create(['name' => 'Test Server']);
    $map = Map::factory()->create();
    $player = Player::factory()->create();

    LapTime::factory()->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $player->id, 'created_at' => now()->subDay()]);

    $this->getJson('/api/v1/servers')
        ->assertOk()
        ->assertJsonFragment([
            'name' => 'Test Server',
            'total_laps' => 1,
            'total_players' => 1,
            'maps_played' => 1,
        ]);
});

it('excludes soft-deleted servers from the list', function () {
    $archived = Server::factory()->create();
    $archived->delete();

    $response = $this->getJson('/api/v1/servers')->assertOk();

    expect(collect($response->json('data'))->pluck('id'))->not->toContain($archived->id);
});

it('does not leak the old API\'s trailing-space "name " key bug', function () {
    Server::factory()->create();

    $response = $this->getJson('/api/v1/servers')->assertOk();

    expect(array_keys($response->json('data.0')))->not->toContain('name ');
});

it('returns the global map leaderboard, ranked by best lap across all active servers', function () {
    $map = Map::factory()->create();
    $serverA = Server::factory()->create();
    $serverB = Server::factory()->create();
    $leader = Player::factory()->create(['name' => 'Leader']);
    $runnerUp = Player::factory()->create(['name' => 'RunnerUp']);

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $serverA->id, 'player_id' => $leader->id, 'time' => 50]);
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $serverB->id, 'player_id' => $runnerUp->id, 'time' => 55.5]);

    $this->getJson("/api/v1/maps/{$map->id}/leaderboard")
        ->assertOk()
        ->assertJsonPath('data.0.rank', 1)
        ->assertJsonPath('data.0.player.name', 'Leader')
        ->assertJsonPath('data.0.gap', 0)
        ->assertJsonPath('data.1.rank', 2)
        ->assertJsonPath('data.1.player.name', 'RunnerUp')
        ->assertJsonPath('data.1.gap', 5.5);
});

it('scopes the map leaderboard to one server via the server query parameter', function () {
    $map = Map::factory()->create();
    $serverA = Server::factory()->create();
    $serverB = Server::factory()->create();
    $onServerA = Player::factory()->create(['name' => 'OnServerA']);
    $onServerB = Player::factory()->create(['name' => 'OnServerB']);

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $serverA->id, 'player_id' => $onServerA->id, 'time' => 50]);
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $serverB->id, 'player_id' => $onServerB->id, 'time' => 40]);

    $response = $this->getJson("/api/v1/maps/{$map->id}/leaderboard?server={$serverA->id}")->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.player.name'))->toBe('OnServerA');
});

it('excludes laps on soft-deleted servers from the map leaderboard', function () {
    $map = Map::factory()->create();
    $archivedServer = Server::factory()->create();
    $player = Player::factory()->create();

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $archivedServer->id, 'player_id' => $player->id]);
    $archivedServer->delete();

    $this->getJson("/api/v1/maps/{$map->id}/leaderboard")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns 404 for a map that does not exist', function () {
    $this->getJson('/api/v1/maps/999999/leaderboard')->assertNotFound();
});

it('shows a single real lap\'s detail, including splits', function () {
    $map = Map::factory()->create(['label' => 'Test Map']);
    $server = Server::factory()->create(['name' => 'Test Server']);
    $player = Player::factory()->create(['name' => 'Test Player']);
    $lap = LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $player->id, 'time' => 45.5]);

    LapTimeSplit::factory()->create(['lap_time_id' => $lap->id, 'checkpoint_id' => 1, 'duration' => 5.5]);

    $this->getJson("/api/v1/laps/{$lap->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $lap->id)
        ->assertJsonPath('data.time', 45.5)
        ->assertJsonPath('data.player.name', 'Test Player')
        ->assertJsonPath('data.map.label', 'Test Map')
        ->assertJsonPath('data.server.name', 'Test Server')
        ->assertJsonCount(1, 'data.splits')
        ->assertJsonPath('data.splits.0.checkpoint_id', 1);
});

it('still returns a lap belonging to a since-archived server', function () {
    $server = Server::factory()->create();
    $lap = LapTime::factory()->create(['server_id' => $server->id]);
    $server->delete();

    // A lap's historical existence doesn't depend on whether its server was later archived —
    // deliberately different from the leaderboard-ranking exclusion rule tested above.
    $this->getJson("/api/v1/laps/{$lap->id}")->assertOk();
});

it('returns 404 for a lap that does not exist', function () {
    $this->getJson('/api/v1/laps/999999')->assertNotFound();
});
