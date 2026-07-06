<?php

use App\Models\LapTime;
use App\Models\Map;
use App\Models\Player;
use App\Models\RecordHistory;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('treats a map\'s very first lap as a record-breaking event with no previous time', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();
    $player = Player::factory()->create();

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $player->id, 'time' => 60]);

    $events = RecordHistory::events();

    expect($events)->toHaveCount(1)
        ->and($events[0]['previousTimeRaw'])->toBeNull();
});

it('records a new event only when a lap is strictly faster than the previous record', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();
    $playerA = Player::factory()->create();
    $playerB = Player::factory()->create();
    $playerC = Player::factory()->create();

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $playerA->id, 'time' => 60, 'created_at' => now()->subDays(3)]);
    // Slower — does not break the record.
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $playerB->id, 'time' => 65, 'created_at' => now()->subDays(2)]);
    // Tied — does not break the record (ties don't count, matching this app's convention elsewhere).
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $playerB->id, 'time' => 60, 'created_at' => now()->subDays(2)]);
    // Faster — breaks it.
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $playerC->id, 'time' => 50, 'created_at' => now()->subDay()]);

    $events = RecordHistory::events();

    expect($events)->toHaveCount(2)
        ->and($events[0]['playerId'])->toBe($playerA->id)
        ->and($events[1]['playerId'])->toBe($playerC->id)
        ->and($events[1]['previousTimeRaw'])->toBe(60.0);
});

it('tracks record history independently per map', function () {
    $mapA = Map::factory()->create();
    $mapB = Map::factory()->create();
    $server = Server::factory()->create();
    $player = Player::factory()->create();

    LapTime::factory()->create(['map_id' => $mapA->id, 'server_id' => $server->id, 'player_id' => $player->id, 'time' => 40]);
    LapTime::factory()->create(['map_id' => $mapB->id, 'server_id' => $server->id, 'player_id' => $player->id, 'time' => 90]);

    expect(RecordHistory::events())->toHaveCount(2);
});

it('excludes laps on soft-deleted servers', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();
    $player = Player::factory()->create();
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $player->id, 'time' => 40]);

    $server->delete();

    expect(RecordHistory::events())->toBe([]);
});

it('returns the most recent events first, optionally windowed by recency', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();
    $playerA = Player::factory()->create();
    $playerB = Player::factory()->create();

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $playerA->id, 'time' => 60, 'created_at' => now()->subDays(20)]);
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $playerB->id, 'time' => 50, 'created_at' => now()->subDays(2)]);

    $recentAll = RecordHistory::recent(5);
    expect($recentAll)->toHaveCount(2)
        ->and($recentAll[0]['playerId'])->toBe($playerB->id); // most recent first

    $recentWindowed = RecordHistory::recent(5, 7);
    expect($recentWindowed)->toHaveCount(1)
        ->and($recentWindowed[0]['playerId'])->toBe($playerB->id);
});

it('respects the limit passed to recent()', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();

    // Each successive (more recent) lap is faster than the last, so every one is a genuine
    // new record — $i counts down from oldest (4 hours ago, slowest) to newest (1 hour ago,
    // fastest).
    foreach (range(1, 4) as $i) {
        $player = Player::factory()->create();
        LapTime::factory()->create([
            'map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $player->id,
            'time' => $i * 10, 'created_at' => now()->subHours($i),
        ]);
    }

    expect(RecordHistory::recent(2))->toHaveCount(2);
});

it('finds a player\'s first-ever record-breaking event', function () {
    $mapA = Map::factory()->create();
    $mapB = Map::factory()->create();
    $server = Server::factory()->create();
    $player = Player::factory()->create();

    LapTime::factory()->create(['map_id' => $mapA->id, 'server_id' => $server->id, 'player_id' => $player->id, 'time' => 60, 'created_at' => now()->subDays(10)]);
    LapTime::factory()->create(['map_id' => $mapB->id, 'server_id' => $server->id, 'player_id' => $player->id, 'time' => 70, 'created_at' => now()->subDays(3)]);

    $first = RecordHistory::firstRecordFor($player->id);

    expect($first['mapId'])->toBe($mapA->id);
});

it('returns null from firstRecordFor when a player has never held a record', function () {
    $player = Player::factory()->create();

    expect(RecordHistory::firstRecordFor($player->id))->toBeNull();
});
