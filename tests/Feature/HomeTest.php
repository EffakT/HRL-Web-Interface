<?php

use App\Events\LapSubmitted;
use App\Livewire\Home;
use App\Models\LapTime;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

// Roadmap item 16 follow-up — every submitted lap can change Quick Stats/highlights, not just a
// PB on one map, so this listens on the site-wide `activity` channel rather than a map-scoped
// one. The listener itself is what runs when the browser's Echo client receives the event;
// there's no running WebSocket server in Pest to exercise the real transport (see decisions.md).
it('re-fetches Quick Stats and highlights when its live-update listener fires', function () {
    $component = Livewire::test(Home::class);
    expect($component->get('quickStats')['players'])->toBe(0);

    Player::factory()->create();

    // A real lap submission fires LapSubmitted, which `App\Listeners\InvalidateHomeHighlightsCache`
    // reacts to by forgetting the cached highlights (PERF-01 follow-up — see Home::CACHE_KEY).
    // Without this, loadHighlights() below would just return the still-cached result from the
    // render above, since nothing else invalidates it.
    event(new LapSubmitted(1, 1));

    $component->call('loadHighlights');

    expect($component->get('quickStats')['players'])->toBe(1);
});

// PERF-01 follow-up (2026-07-08) — Home's highlights/quick-stats are now cached (see
// Home::CACHE_KEY's docblock: real profiling showed one computation costs ~1.5s/94 queries at
// real scale, and every homepage visitor between two lap submissions was paying that
// independently). These two tests cover the caching mechanics directly, distinct from the test
// above, which covers what happens once the cache *has* been invalidated. The actual cache key
// is CACHE_KEY suffixed with the current generation (see Home::GENERATION_KEY's docblock for
// why a plain fixed key isn't safe), not CACHE_KEY itself.
it('serves a fresh render from cache, not recomputed, on a second component instance', function () {
    Player::factory()->create();

    Livewire::test(Home::class);
    $generation = Cache::get(Home::GENERATION_KEY, 0);
    expect(Cache::has(Home::CACHE_KEY.':'.$generation))->toBeTrue();

    // A second player exists now, but nothing has invalidated the cache — a fresh component
    // instance (simulating a second visitor loading the page) must still see the cached figure,
    // not recompute against the database's current state.
    Player::factory()->create();

    $secondVisitor = Livewire::test(Home::class);

    expect($secondVisitor->get('quickStats')['players'])->toBe(1);
});

it('bumps the generation counter when LapSubmitted fires, invalidating the cached key', function () {
    Player::factory()->create();

    Livewire::test(Home::class);
    $generationBefore = Cache::get(Home::GENERATION_KEY, 0);
    expect(Cache::has(Home::CACHE_KEY.':'.$generationBefore))->toBeTrue();

    event(new LapSubmitted(1, 1));

    $generationAfter = Cache::get(Home::GENERATION_KEY, 0);
    expect($generationAfter)->toBeGreaterThan($generationBefore)
        ->and(Cache::has(Home::CACHE_KEY.':'.$generationAfter))->toBeFalse();
});

// Reproduces the real race caught before this shipped (see Home::GENERATION_KEY's docblock): a
// rebuild that was already in flight when a new lap invalidated the cache must not resurrect
// stale data by writing it back after the fact. True concurrency isn't reproducible in a
// synchronous test process, but the actual invariant the generation key provides — a write
// against an *old* generation can never become what a *current* read sees — is: simulated here
// by planting a stale write under the pre-bump generation directly, matching exactly what a
// slow, already-in-flight `computeHighlights()` call finishing late would do.
it('never serves a stale write made against an old generation after invalidation', function () {
    Player::factory()->create();

    Livewire::test(Home::class);
    $staleGeneration = Cache::get(Home::GENERATION_KEY, 0);

    event(new LapSubmitted(1, 1));

    // Simulates the old generation's in-flight rebuild finishing *after* invalidation and
    // writing its now-outdated result — this must land on the abandoned old-generation key,
    // never on whatever key a fresh read now uses.
    Cache::put(Home::CACHE_KEY.':'.$staleGeneration, [
        'highlights' => [],
        'quickStats' => ['players' => 999, 'servers' => 999, 'laps' => 999],
    ], now()->addMinutes(10));

    Player::factory()->create();
    Player::factory()->create();

    $freshVisitor = Livewire::test(Home::class);

    expect($freshVisitor->get('quickStats')['players'])->toBe(3);
});
