<?php

use App\Livewire\Home;
use App\Models\LapTime;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

it('shows real Quick Stats counts', function () {
    $players = Player::factory()->count(3)->create();
    $servers = Server::factory()->count(2)->create();
    $map = Map::factory()->create();

    // Explicit server_id/player_id — LapTimeFactory's default state spawns its own nested
    // Server/Player if not overridden, which would silently inflate the counts above.
    LapTime::factory()->count(4)->create([
        'map_id' => $map->id,
        'server_id' => $servers->first()->id,
        'player_id' => $players->first()->id,
    ]);

    $component = Livewire::test(Home::class);

    expect($component->get('quickStats'))->toBe([
        'players' => 3,
        'servers' => 2,
        'laps' => 4,
    ]);
});

it('falls back to Live Stats Snapshot alone when every other candidate is empty', function () {
    // A completely bare database: no maps/servers/laps means every windowed/derived highlight
    // (records, most-active-server, fastest-improvements, new-content, achievements) has
    // nothing to show — only Live Stats Snapshot (plain aggregate counts, never truly "empty")
    // should appear.
    $highlights = Livewire::test(Home::class)->get('highlights');

    expect($highlights)->toHaveCount(1)
        ->and($highlights[0]['type'])->toBe('live-stats');
});

it('does not highlight a "biggest improvement" that lands at a non-competitive rank', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();
    $player = Player::factory()->create(['name' => 'Sandbagger']);

    // 55 real competitors, all faster than the sandbagger's eventual "improved" time —
    // guarantees their final rank is past 50 (earns zero points).
    for ($i = 0; $i < 55; $i++) {
        LapTime::factory()->create([
            'map_id' => $map->id, 'server_id' => $server->id,
            'player_id' => Player::factory()->create()->id,
            'time' => 50 + $i, 'created_at' => now()->subDays(30),
        ]);
    }

    // Deliberately terrible first lap (nothing to beat yet, trivially becomes their "PB"),
    // then a merely-mediocre lap this week — a huge-looking delta, still uncompetitive result.
    LapTime::factory()->create([
        'map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $player->id,
        'time' => 500, 'created_at' => now()->subDays(10),
    ]);
    LapTime::factory()->create([
        'map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $player->id,
        'time' => 150, 'created_at' => now()->subDays(1),
    ]);

    $highlights = collect(Livewire::test(Home::class)->get('highlights'));
    $fastestImprovements = $highlights->firstWhere('type', 'fastest-improvements');

    expect($fastestImprovements)->toBeNull();
});

it('prefers a distinct lap for the rank-jump highlight over repeating the biggest-improvement lap', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();
    $improver = Player::factory()->create(['name' => 'BigImprover']);
    $jumper = Player::factory()->create(['name' => 'SmallJumper']);

    // Fixed baseline field: ranks 1-5 at 50/55/60/65/70.
    foreach ([50, 55, 60, 65, 70] as $time) {
        LapTime::factory()->create([
            'map_id' => $map->id, 'server_id' => $server->id,
            'player_id' => Player::factory()->create()->id, 'time' => $time,
        ]);
    }

    // Improver: old lap at 200s (last place), improves to 45s this week — huge delta (155s)
    // AND the single biggest rank jump (last place to #1). Should win "biggest improvement".
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $improver->id, 'time' => 200, 'created_at' => now()->subDays(10)]);
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $improver->id, 'time' => 45, 'created_at' => now()->subDays(1)]);

    // Jumper: old lap at 62s (rank 4, between 60 and 65), improves to 58s this week — a real
    // but much smaller jump (rank 4 to rank 3, since 45/50/55/58/60/65/70 puts them 4th... the
    // exact rank number doesn't matter, only that it's a genuine, smaller, DISTINCT jump).
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $jumper->id, 'time' => 62, 'created_at' => now()->subDays(10)]);
    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $jumper->id, 'time' => 58, 'created_at' => now()->subDays(1)]);

    $fastestImprovements = collect(Livewire::test(Home::class)->get('highlights'))->firstWhere('type', 'fastest-improvements');
    $texts = collect($fastestImprovements['data'])->pluck('text')->implode(' | ');

    expect($texts)->toContain('BigImprover')
        ->toContain('SmallJumper'); // distinct player, not BigImprover's jump repeated
});

it('shows a real first-ever course record as a Player Achievement', function () {
    // 10 background players, each ranked #1 (solo) on 2 dedicated maps of their own — 200
    // Global Score each, comfortably ahead of what a single new record on one map can produce
    // (100). This keeps them ahead of our test player in the global Top 10, so Fastest
    // Improvements' own "new Top 10 entry" sub-item doesn't also fire and crowd Achievements
    // out of the top 3 (both blocks would otherwise report the same underlying event).
    foreach (range(1, 10) as $i) {
        $backgroundPlayer = Player::factory()->create();

        foreach (range(1, 2) as $j) {
            $backgroundMap = Map::factory()->create(['created_at' => now()->subDays(60)]);
            $backgroundServer = Server::factory()->create(['created_at' => now()->subDays(60)]);

            LapTime::factory()->create([
                'map_id' => $backgroundMap->id, 'server_id' => $backgroundServer->id,
                'player_id' => $backgroundPlayer->id, 'time' => 50, 'created_at' => now()->subDays(60),
            ]);
        }
    }

    // Test player's first-ever lap, this week — trivially becomes the record on their own map.
    $map = Map::factory()->create(['created_at' => now()->subDays(60)]);
    $server = Server::factory()->create(['created_at' => now()->subDays(60)]);
    $player = Player::factory()->create(['name' => 'FirstTimer']);

    LapTime::factory()->create([
        'map_id' => $map->id, 'server_id' => $server->id, 'player_id' => $player->id,
        'time' => 45, 'created_at' => now()->subDays(2),
    ]);

    $achievements = collect(Livewire::test(Home::class)->get('highlights'))->firstWhere('type', 'achievements');

    expect($achievements)->not->toBeNull();
    expect(collect($achievements['data'])->pluck('note')->implode(' '))->toContain('first-ever course record');
});

it('excludes servers with no real activity from the Most Active Server highlight', function () {
    $activeServer = Server::factory()->create();
    $inactiveServer = Server::factory()->create();
    $map = Map::factory()->create();
    $player = Player::factory()->create();

    LapTime::factory()->create(['map_id' => $map->id, 'server_id' => $activeServer->id, 'player_id' => $player->id, 'created_at' => now()->subDays(2)]);

    $mostActive = collect(Livewire::test(Home::class)->get('highlights'))->firstWhere('type', 'most-active-server');

    expect($mostActive)->not->toBeNull()
        ->and(collect($mostActive['data'])->pluck('name'))->toContain($activeServer->name)
        ->not->toContain($inactiveServer->name);
});
