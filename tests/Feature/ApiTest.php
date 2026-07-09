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
    $this->getJson('/api/v1/laps/999999')->assertNotFound();
});
