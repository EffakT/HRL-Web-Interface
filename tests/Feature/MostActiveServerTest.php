<?php

use App\Models\LapTime;
use App\Models\Map;
use App\Models\MostActiveServer;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('computes the Activity Score formula', function () {
    $server = Server::factory()->create();
    $map = Map::factory()->create();
    $playerA = Player::factory()->create();
    $playerB = Player::factory()->create();

    // 2 unique players, 1 map played, 2 valid (player,map) participations.
    LapTime::factory()->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $playerA->id]);
    LapTime::factory()->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $playerB->id]);

    $score = MostActiveServer::forServer($server->id);

    // (2 unique players * 10) + (2 valid laps * 1) + (1 map * 20) = 42
    expect($score['activityScore'])->toBe(42);
});

it('counts Valid Laps as distinct (player, map) participations, not raw lap count', function () {
    $server = Server::factory()->create();
    $map = Map::factory()->create();
    $player = Player::factory()->create();

    // Same player grinding the same map 5 times shouldn't inflate Valid Laps past 1 —
    // otherwise one dedicated player could dominate the score via repetition alone.
    LapTime::factory()->count(5)->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $player->id]);

    $score = MostActiveServer::forServer($server->id);

    expect($score['validLaps'])->toBe(1)
        ->and($score['uniquePlayers'])->toBe(1)
        ->and($score['mapsPlayed'])->toBe(1);
});

it('ignores laps older than the 90-day base window', function () {
    $server = Server::factory()->create();
    $map = Map::factory()->create();
    $player = Player::factory()->create();

    LapTime::factory()->create([
        'server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $player->id,
        'created_at' => now()->subDays(91),
    ]);

    $score = MostActiveServer::forServer($server->id);

    expect($score['activityScore'])->toBe(0)
        ->and($score['uniquePlayers'])->toBe(0);
});

it('applies only the highest recency bonus tier', function () {
    $sevenDayServer = Server::factory()->create();
    $thirtyDayServer = Server::factory()->create();
    $ninetyDayServer = Server::factory()->create();
    $inactiveServer = Server::factory()->create();
    $map = Map::factory()->create();
    $player = Player::factory()->create();

    LapTime::factory()->create(['server_id' => $sevenDayServer->id, 'map_id' => $map->id, 'player_id' => $player->id, 'created_at' => now()->subDays(3)]);
    LapTime::factory()->create(['server_id' => $thirtyDayServer->id, 'map_id' => $map->id, 'player_id' => $player->id, 'created_at' => now()->subDays(20)]);
    LapTime::factory()->create(['server_id' => $ninetyDayServer->id, 'map_id' => $map->id, 'player_id' => $player->id, 'created_at' => now()->subDays(60)]);

    expect(MostActiveServer::forServer($sevenDayServer->id)['recencyBonus'])->toBe(100)
        ->and(MostActiveServer::forServer($thirtyDayServer->id)['recencyBonus'])->toBe(50)
        ->and(MostActiveServer::forServer($ninetyDayServer->id)['recencyBonus'])->toBe(20)
        ->and(MostActiveServer::forServer($inactiveServer->id)['recencyBonus'])->toBe(0);
});

it('breaks an equal-score tie in favor of the more recently active server', function () {
    $olderActivity = Server::factory()->create();
    $recentActivity = Server::factory()->create();
    $map = Map::factory()->create();
    $player = Player::factory()->create();

    // Identical activity profile (same player/map/lap counts) on both servers, so their
    // Activity Score and recency bonus tier are exactly equal — the only real difference is
    // which one raced more recently, which is the final tie-break rule.
    LapTime::factory()->create(['server_id' => $olderActivity->id, 'map_id' => $map->id, 'player_id' => $player->id, 'created_at' => now()->subDays(5)]);
    LapTime::factory()->create(['server_id' => $recentActivity->id, 'map_id' => $map->id, 'player_id' => $player->id, 'created_at' => now()->subDays(1)]);

    $scores = collect(MostActiveServer::scores())->keyBy('serverId');

    expect($scores[$olderActivity->id]['totalScore'])->toBe($scores[$recentActivity->id]['totalScore'])
        ->and($scores[$recentActivity->id]['rank'])->toBeLessThan($scores[$olderActivity->id]['rank']);
});

it('excludes laps on soft-deleted servers entirely', function () {
    $server = Server::factory()->create();
    $map = Map::factory()->create();
    $player = Player::factory()->create();
    LapTime::factory()->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $player->id]);

    $server->delete();

    expect(MostActiveServer::forServer($server->id))->toBeNull();
});

it('computes display-only 30d/90d unique-player counts independent of the score window', function () {
    $server = Server::factory()->create();
    $map = Map::factory()->create();
    $recentPlayer = Player::factory()->create();
    $olderPlayer = Player::factory()->create();

    LapTime::factory()->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $recentPlayer->id, 'created_at' => now()->subDays(5)]);
    LapTime::factory()->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $olderPlayer->id, 'created_at' => now()->subDays(45)]);

    $score = MostActiveServer::forServer($server->id);

    expect($score['players30d'])->toBe(1)
        ->and($score['players90d'])->toBe(2);
});
