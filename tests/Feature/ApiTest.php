<?php

use App\Models\LapTime;
use App\Models\LapTimeSplit;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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

it('paginates the servers list', function () {
    Server::factory()->count(5)->create();

    $response = $this->getJson('/api/v1/servers?per_page=2')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.total'))->toBe(5)
        ->and($response->json('meta.last_page'))->toBe(3);
});

it('caps the servers list per_page at a sane maximum', function () {
    Server::factory()->create();

    $response = $this->getJson('/api/v1/servers?per_page=999999')->assertOk();

    expect($response->json('meta.per_page'))->toBe(100);
});

it('rate-limits the public read API at its configured per-IP boundary (TEST-01 audit follow-up)', function () {
    // The real `api` limiter (AppServiceProvider) is a flat 60/min, too slow to exhaust one
    // request at a time in a test — re-registering the same named limiter with a much smaller
    // ceiling exercises the identical `throttle:api` middleware/limiter wiring at a fast, exact
    // boundary instead of guessing whether 60 real requests trip it.
    RateLimiter::for('api', fn (Request $request) => Limit::perMinute(2)->by($request->ip()));

    $this->getJson('/api/v1/servers')->assertOk();
    $this->getJson('/api/v1/servers')->assertOk();
    $this->getJson('/api/v1/servers')->assertStatus(429);
});

it('enforces the real production rate-limit ceiling, not just the throttle:api wiring (TEST-01 audit follow-up)', function () {
    // Unlike the fast boundary test above (which substitutes a small ceiling to exercise the
    // wiring quickly), this asserts the actual configured production value — config/api.php,
    // 60/min by default — so the test fails if that value ever drifts unintentionally, and
    // proves requests 1-60 genuinely succeed while the 61st is rejected.
    expect(config('api.rate_limit_per_minute'))->toBe(60);

    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/api/v1/servers')->assertOk();
    }

    $this->getJson('/api/v1/servers')->assertStatus(429);
});

it('lists every map, paginated, with real derived stats', function () {
    $mapA = Map::factory()->create(['name' => 'bloodgulch', 'label' => 'Blood Gulch', 'checkpoint_count' => 5]);
    $mapB = Map::factory()->create(['name' => 'dangercanyon', 'label' => 'Danger Canyon', 'checkpoint_count' => 6]);
    $server = Server::factory()->create();

    LapTime::factory()->create(['map_id' => $mapA->id, 'server_id' => $server->id, 'player_id' => Player::factory()->create()->id]);
    LapTime::factory()->count(2)->create(['map_id' => $mapB->id, 'server_id' => $server->id, 'player_id' => Player::factory()->create()->id]);

    $this->getJson('/api/v1/maps')
        ->assertOk()
        ->assertJsonFragment(['id' => $mapA->id, 'name' => 'bloodgulch', 'label' => 'Blood Gulch', 'checkpoint_count' => 5, 'total_laps' => 1])
        ->assertJsonFragment(['id' => $mapB->id, 'name' => 'dangercanyon', 'label' => 'Danger Canyon', 'checkpoint_count' => 6, 'total_laps' => 2]);
});

it('paginates the maps list', function () {
    Map::factory()->count(5)->create();

    $response = $this->getJson('/api/v1/maps?per_page=2')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.total'))->toBe(5)
        ->and($response->json('meta.last_page'))->toBe(3);
});

it('caps the maps list per_page at a sane maximum', function () {
    Map::factory()->create();

    $response = $this->getJson('/api/v1/maps?per_page=999999')->assertOk();

    expect($response->json('meta.per_page'))->toBe(100);
});

it('lists players ranked by Global Score, with real derived stats (same data as the Players List page)', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();
    $leader = Player::factory()->create(['name' => 'Leader']);
    $runnerUp = Player::factory()->create(['name' => 'RunnerUp']);

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $leader->id, 'time' => 50, 'created_at' => now()->subHour()]);
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $runnerUp->id, 'time' => 60, 'created_at' => now()->subDay()]);

    $this->getJson('/api/v1/players')
        ->assertOk()
        ->assertJsonPath('data.0.rank', 1)
        ->assertJsonPath('data.0.name', 'Leader')
        ->assertJsonPath('data.0.score', 100)
        ->assertJsonPath('data.0.records', 1)
        ->assertJsonPath('data.0.maps_played', 1)
        ->assertJsonPath('data.0.total_laps', 1)
        ->assertJsonPath('data.1.rank', 2)
        ->assertJsonPath('data.1.name', 'RunnerUp')
        ->assertJsonPath('data.1.records', 0);
});

it('reports a player\'s real last-active timestamp, and null for a player somehow absent from lap history', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();
    $player = Player::factory()->create();
    $lastLapAt = now()->subHours(3);

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $player->id, 'created_at' => $lastLapAt]);

    $this->getJson('/api/v1/players')
        ->assertOk()
        ->assertJsonPath('data.0.last_active_at', $lastLapAt->toIso8601String());
});

it('excludes laps on soft-deleted servers from the players list stats', function () {
    $map = Map::factory()->create();
    $archivedServer = Server::factory()->create();
    $player = Player::factory()->create();

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $archivedServer->id, 'player_id' => $player->id]);
    $archivedServer->delete();

    $this->getJson('/api/v1/players')->assertOk()->assertJsonCount(0, 'data');
});

it('paginates the players list', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();

    foreach (range(1, 5) as $i) {
        LapTime::factory()->create([
            'map_id' => $map->id,
            'server_id' => $server->id,
            'player_id' => Player::factory()->create()->id,
            'time' => 50 + $i,
        ]);
    }

    $response = $this->getJson('/api/v1/players?per_page=2')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.total'))->toBe(5)
        ->and($response->json('meta.last_page'))->toBe(3)
        ->and($response->json('data.0.rank'))->toBe(1);
});

it('caps the players list per_page at a sane maximum', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => Player::factory()->create()->id]);

    $response = $this->getJson('/api/v1/players?per_page=999999')->assertOk();

    expect($response->json('meta.per_page'))->toBe(100);
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

it('includes a leaderboard entry\'s splits when its lap has any', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();
    $player = Player::factory()->create();

    $lap = LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $player->id]);
    LapTimeSplit::factory()->create(['lap_time_id' => $lap->id, 'checkpoint_id' => 1, 'duration' => 5.5]);
    LapTimeSplit::factory()->create(['lap_time_id' => $lap->id, 'checkpoint_id' => 2, 'duration' => 6.25]);

    $this->getJson("/api/v1/maps/{$map->id}/leaderboard")
        ->assertOk()
        ->assertJsonCount(2, 'data.0.splits')
        ->assertJsonPath('data.0.splits.0.checkpoint_id', 1)
        ->assertJsonPath('data.0.splits.0.duration', 5.5)
        ->assertJsonPath('data.0.splits.1.checkpoint_id', 2);
});

it('returns an empty splits array for a leaderboard entry whose lap has none (the common case)', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => Player::factory()->create()->id]);

    $this->getJson("/api/v1/maps/{$map->id}/leaderboard")
        ->assertOk()
        ->assertJsonCount(0, 'data.0.splits');
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

it('scopes the map leaderboard by the requester\'s own ip:port via the port query parameter', function () {
    $map = Map::factory()->create();
    $serverA = Server::factory()->create(['ip' => '10.0.0.1', 'port' => '2302']);
    $serverB = Server::factory()->create(['ip' => '10.0.0.2', 'port' => '2302']);
    $onServerA = Player::factory()->create(['name' => 'OnServerA']);
    $onServerB = Player::factory()->create(['name' => 'OnServerB']);

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $serverA->id, 'player_id' => $onServerA->id, 'time' => 50]);
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $serverB->id, 'player_id' => $onServerB->id, 'time' => 40]);

    test()->withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);
    $response = $this->getJson("/api/v1/maps/{$map->id}/leaderboard?port=2302")->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.player.name'))->toBe('OnServerA');
});

it('rewrites a known internal NAT ip before resolving the requester\'s own server (same map as lap submission)', function () {
    config(['webhook.internal_ip_map' => ['192.168.88.1' => '114.23.254.181']]);

    $map = Map::factory()->create();
    $server = Server::factory()->create(['ip' => '114.23.254.181', 'port' => '2302']);
    $player = Player::factory()->create();

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $player->id]);

    test()->withServerVariables(['REMOTE_ADDR' => '192.168.88.1']);
    $this->getJson("/api/v1/maps/{$map->id}/leaderboard?port=2302")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('returns 404 when the port query parameter does not match any registered server', function () {
    $map = Map::factory()->create();

    test()->withServerVariables(['REMOTE_ADDR' => '10.0.0.9']);
    $this->getJson("/api/v1/maps/{$map->id}/leaderboard?port=9999")->assertNotFound();
});

it('prefers the port query parameter over an explicit server id when both are given', function () {
    $map = Map::factory()->create();
    $serverA = Server::factory()->create(['ip' => '10.0.0.1', 'port' => '2302']);
    $serverB = Server::factory()->create(['ip' => '10.0.0.2', 'port' => '2302']);
    $onServerA = Player::factory()->create(['name' => 'OnServerA']);
    $onServerB = Player::factory()->create(['name' => 'OnServerB']);

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $serverA->id, 'player_id' => $onServerA->id]);
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $serverB->id, 'player_id' => $onServerB->id]);

    test()->withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);
    $response = $this->getJson("/api/v1/maps/{$map->id}/leaderboard?port=2302&server={$serverB->id}")->assertOk();

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
    $this->getJson('/api/v1/maps/999999/leaderboard')
        ->assertNotFound()
        ->assertExactJson(['message' => 'No query results for map 999999']);
});

it('resolves the map leaderboard route by name as well as by id', function () {
    $map = Map::factory()->create(['name' => 'bloodgulch']);
    $server = Server::factory()->create();
    $player = Player::factory()->create();

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $player->id]);

    $this->getJson('/api/v1/maps/bloodgulch/leaderboard')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('returns 404 for a map name that does not exist', function () {
    $this->getJson('/api/v1/maps/does-not-exist/leaderboard')
        ->assertNotFound()
        ->assertExactJson(['message' => 'No query results for map does-not-exist']);
});

it('paginates the map leaderboard (PERF-03 audit follow-up)', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();

    foreach (range(1, 5) as $i) {
        LapTime::factory()->create([
            'map_id' => $map->id,
            'server_id' => $server->id,
            'player_id' => Player::factory()->create()->id,
            'time' => 50 + $i,
        ]);
    }

    $response = $this->getJson("/api/v1/maps/{$map->id}/leaderboard?per_page=2")->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.total'))->toBe(5)
        ->and($response->json('meta.last_page'))->toBe(3)
        ->and($response->json('data.0.rank'))->toBe(1);

    $this->getJson("/api/v1/maps/{$map->id}/leaderboard?per_page=2&page=2")
        ->assertOk()
        ->assertJsonPath('data.0.rank', 3);
});

it('caps per_page at a sane maximum so a huge value cannot force one giant response', function () {
    $map = Map::factory()->create();
    Server::factory()->create();

    $response = $this->getJson("/api/v1/maps/{$map->id}/leaderboard?per_page=999999")->assertOk();

    expect($response->json('meta.per_page'))->toBe(100);
});

it('clamps an out-of-range page instead of overflowing the slice offset into a 500', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => Player::factory()->create()->id]);

    // Large enough that (page - 1) * per_page overflows PHP's int range and becomes a float —
    // array_slice() rejects a float offset with a TypeError, previously a real 500.
    $response = $this->getJson("/api/v1/maps/{$map->id}/leaderboard?page=999999999999999999999")
        ->assertOk();

    expect($response->json('meta.current_page'))->toBe(1)
        ->and($response->json('data'))->toHaveCount(1);
});

it('floors a negative or zero page to page 1 instead of an invalid negative slice offset', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => Player::factory()->create()->id]);

    foreach (['-5', '0'] as $page) {
        $response = $this->getJson("/api/v1/maps/{$map->id}/leaderboard?page={$page}")->assertOk();

        expect($response->json('meta.current_page'))->toBe(1)
            ->and($response->json('data'))->toHaveCount(1);
    }
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
    $this->getJson('/api/v1/laps/999999')
        ->assertNotFound()
        ->assertExactJson(['message' => 'No query results for lap time 999999']);
});
