<?php

use App\Models\GlobalRanking;
use App\Models\LapTime;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

// GLOBAL_RANKING_VARIANT is pinned to "sum" in phpunit.xml so these tests never depend on
// whatever a developer currently has set in .env for manual A/B comparison (see docs/decisions.md).
uses(LazilyRefreshDatabase::class);

it('assigns the fixed top-10 points table', function () {
    expect(GlobalRanking::pointsForRank(1))->toBe(100)
        ->and(GlobalRanking::pointsForRank(2))->toBe(95)
        ->and(GlobalRanking::pointsForRank(10))->toBe(68);
});

it('interpolates points for ranks 11-25 and 26-50, and awards zero past 50', function () {
    expect(GlobalRanking::pointsForRank(11))->toBe(66)
        ->and(GlobalRanking::pointsForRank(25))->toBe(40)
        ->and(GlobalRanking::pointsForRank(26))->toBe(39)
        ->and(GlobalRanking::pointsForRank(50))->toBe(10)
        ->and(GlobalRanking::pointsForRank(51))->toBe(0)
        ->and(GlobalRanking::pointsForRank(1000))->toBe(0);
});

it('sums points across every map a player has raced', function () {
    $map1 = Map::factory()->create();
    $map2 = Map::factory()->create();
    $server = Server::factory()->create();
    $player = Player::factory()->create(['name' => 'Solo Racer']);

    LapTime::factory()->create(['map_id' => $map1->id, 'server_id' => $server->id, 'player_id' => $player->id, 'time' => 50]);
    LapTime::factory()->create(['map_id' => $map2->id, 'server_id' => $server->id, 'player_id' => $player->id, 'time' => 60]);

    $scores = GlobalRanking::scores();

    expect($scores)->toHaveCount(1)
        ->and($scores[0]['score'])->toBe(200) // rank 1 on both maps: 100 + 100
        ->and($scores[0]['mapsPlayed'])->toBe(2)
        ->and($scores[0]['firstPlaces'])->toBe(2);
});

it('ranks players by best lap per map, ignoring their slower attempts', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();
    $player = Player::factory()->create();

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $player->id, 'time' => 60]);
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $player->id, 'time' => 45]);
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $player->id, 'time' => 70]);

    $scores = GlobalRanking::scores();

    expect($scores)->toHaveCount(1)
        ->and($scores[0]['mapsPlayed'])->toBe(1) // one map, not three attempts
        ->and($scores[0]['perMap'][0]['time'])->toBe('0:45.00');
});

it('breaks a tied time in favor of whoever set it first', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();
    $first = Player::factory()->create(['name' => 'First']);
    $second = Player::factory()->create(['name' => 'Second']);

    LapTime::factory()->create([
        'map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $first->id,
        'time' => 50, 'created_at' => now()->subDay(),
    ]);
    LapTime::factory()->create([
        'map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $second->id,
        'time' => 50, 'created_at' => now(),
    ]);

    $ranking = GlobalRanking::forPlayer($first->id);

    expect($ranking['perMap'][0]['rank'])->toBe(1)
        ->and(GlobalRanking::forPlayer($second->id)['perMap'][0]['rank'])->toBe(2);
});

it('excludes laps on soft-deleted servers', function () {
    $map = Map::factory()->create();
    $activeServer = Server::factory()->create();
    $archivedServer = Server::factory()->create();
    $player = Player::factory()->create();

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $archivedServer->id, 'player_id' => $player->id, 'time' => 40]);
    $archivedServer->delete();

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $activeServer->id, 'player_id' => $player->id, 'time' => 60]);

    $ranking = GlobalRanking::forPlayer($player->id);

    expect($ranking['mapsPlayed'])->toBe(1)
        ->and($ranking['perMap'][0]['time'])->toBe('1:00.00');
});

it('excludes a specific lap when excludeLapId is given, for before/after comparisons', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();
    $player = Player::factory()->create();

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $player->id, 'time' => 90]);
    $improvedLap = LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $player->id, 'time' => 40]);

    expect(GlobalRanking::forPlayer($player->id)['perMap'][0]['time'])->toBe('0:40.00')
        ->and(GlobalRanking::forPlayer($player->id, excludeLapId: $improvedLap->id)['perMap'][0]['time'])->toBe('1:30.00');
});

it('scopes to one server for the Server Score variant', function () {
    $map = Map::factory()->create();
    $serverA = Server::factory()->create();
    $serverB = Server::factory()->create();
    $player = Player::factory()->create();

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $serverA->id, 'player_id' => $player->id, 'time' => 50]);
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $serverB->id, 'player_id' => $player->id, 'time' => 55]);

    expect(GlobalRanking::forPlayer($player->id, serverId: $serverA->id)['mapsPlayed'])->toBe(1)
        ->and(GlobalRanking::forPlayer($player->id)['mapsPlayed'])->toBe(1); // one map globally too, just best-of-both
});

it('switches between sum and average score variants via config', function () {
    $map1 = Map::factory()->create();
    $map2 = Map::factory()->create();
    $server = Server::factory()->create();
    $player = Player::factory()->create();
    $rival = Player::factory()->create();

    // Player: rank 1 on both maps (200 raw points). Rival: only races map1, rank 2 there.
    LapTime::factory()->create(['map_id' => $map1->id, 'server_id' => $server->id, 'player_id' => $player->id, 'time' => 40]);
    LapTime::factory()->create(['map_id' => $map2->id, 'server_id' => $server->id, 'player_id' => $player->id, 'time' => 40]);
    LapTime::factory()->create(['map_id' => $map1->id, 'server_id' => $server->id, 'player_id' => $rival->id, 'time' => 50]);

    config(['ranking.global_score_variant' => 'sum']);
    expect(GlobalRanking::forPlayer($player->id)['score'])->toBe(200);

    config(['ranking.global_score_variant' => 'average']);
    // Average variant is a regularized (Bayesian) average, not raw — just confirm it's no
    // longer the raw sum, proving the config switch actually changes behavior.
    expect(GlobalRanking::forPlayer($player->id)['score'])->not->toBe(200);

    config(['ranking.global_score_variant' => 'sum']);
});
