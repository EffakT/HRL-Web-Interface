<?php

use App\Livewire\Servers\ServerList;
use App\Models\LapTime;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

// ServerList was wired to real data (see docs/decisions.md) — several fields from the original
// mock (region, live online status, player capacity, ping) have no real-schema equivalent and
// were dropped or replaced with an honest derived proxy rather than fabricated. These tests
// assert the real derivation, not just that the route renders (RoutesTest.php covers that).
uses(LazilyRefreshDatabase::class);

it('shows real server names and derives "now playing" from the most recent lap\'s map', function () {
    $server = Server::factory()->create(['name' => 'Real Server Name']);
    $map = Map::factory()->create(['label' => 'Real Map Label']);
    $player = Player::factory()->create();

    LapTime::factory()->create([
        'server_id' => $server->id,
        'map_id' => $map->id,
        'player_id' => $player->id,
        'time' => 65.5,
        'created_at' => now(),
    ]);

    $this->get('/servers')
        ->assertSee('Real Server Name')
        ->assertSee('Real Map Label')
        ->assertSee('1:05.50');
});

it('marks a server online only when it has a lap within the last 24 hours', function () {
    $recent = Server::factory()->create(['name' => 'Recently Active Server']);
    $stale = Server::factory()->create(['name' => 'Stale Server']);
    $map = Map::factory()->create();
    $player = Player::factory()->create();

    LapTime::factory()->create([
        'server_id' => $recent->id,
        'map_id' => $map->id,
        'player_id' => $player->id,
        'created_at' => now()->subHours(2),
    ]);
    LapTime::factory()->create([
        'server_id' => $stale->id,
        'map_id' => $map->id,
        'player_id' => $player->id,
        'created_at' => now()->subDays(10),
    ]);

    $rows = collect(Livewire::test(ServerList::class)->get('servers'));

    expect($rows->firstWhere('name', $recent->name)['online'])->toBeTrue()
        ->and($rows->firstWhere('name', $stale->name)['online'])->toBeFalse();
});

it('computes the total-players header stat as distinct players across all servers, not raw lap count', function () {
    $server = Server::factory()->create();
    $map = Map::factory()->create();
    $playerA = Player::factory()->create();
    $playerB = Player::factory()->create();

    // Player A laps twice — should still count once toward "total players".
    LapTime::factory()->count(2)->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $playerA->id]);
    LapTime::factory()->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $playerB->id]);

    $this->get('/servers')->assertSee('2 PLAYERS');
});

// Roadmap item 19 — a fresh, successful live query (App\Console\Commands\RefreshLiveServerInfo)
// should win over the lap-history-derived proxies for "online"/"map".
it('prefers a fresh successful live query over the lap-history proxy for online status and map', function () {
    $liveMap = Map::factory()->create(['label' => 'Live Map']);
    $staleMap = Map::factory()->create(['label' => 'Stale Proxy Map']);
    $player = Player::factory()->create();

    $server = Server::factory()->create([
        'current_map_id' => $liveMap->id,
        'live_player_count' => 4,
        'queried_at' => now()->subMinute(),
        'query_successful' => true,
    ]);

    // A lap from 10 days ago would mark this server offline under the old recency proxy —
    // the fresh live query should win instead.
    LapTime::factory()->create([
        'server_id' => $server->id,
        'map_id' => $staleMap->id,
        'player_id' => $player->id,
        'created_at' => now()->subDays(10),
    ]);

    $row = collect(Livewire::test(ServerList::class)->get('servers'))->firstWhere('id', $server->id);

    expect($row['online'])->toBeTrue()
        ->and($row['map'])->toBe('Live Map');
});

it('still shows a reachable-but-empty server as online in the table, with its live map', function () {
    $liveMap = Map::factory()->create(['label' => 'Empty Server Map']);

    $server = Server::factory()->create([
        'current_map_id' => $liveMap->id,
        'live_player_count' => 0,
        'queried_at' => now()->subMinute(),
        'query_successful' => true,
    ]);

    $row = collect(Livewire::test(ServerList::class)->get('servers'))->firstWhere('id', $server->id);

    // The table's "online" means "the server process is reachable" — that's still true even
    // with nobody on it. The stricter "someone's actually racing" bar only applies to the
    // featured card (see the next test).
    expect($row['online'])->toBeTrue()
        ->and($row['map'])->toBe('Empty Server Map');
});

it('does not feature a reachable-but-empty server as online, even with a fresh successful query', function () {
    $liveMap = Map::factory()->create(['label' => 'Empty Server Map']);

    Server::factory()->create([
        'current_map_id' => $liveMap->id,
        'live_player_count' => 0,
        'queried_at' => now()->subMinute(),
        'query_successful' => true,
    ]);

    $featured = Livewire::test(ServerList::class)->get('featured');

    // The featured card means "you could join a race right now" — a reachable server with
    // nobody on it doesn't qualify, even though the table row above still shows it as online.
    expect($featured['online'])->toBeFalse()
        ->and($featured['map'])->toBe('Empty Server Map');
});

it('treats a fresh failed live query as authoritative for online status, falling back to the proxy map', function () {
    $lastKnownMap = Map::factory()->create(['label' => 'Last Known Map']);
    $player = Player::factory()->create();

    $server = Server::factory()->create([
        'current_map_id' => null,
        'queried_at' => now()->subMinute(),
        'query_successful' => false,
    ]);

    LapTime::factory()->create([
        'server_id' => $server->id,
        'map_id' => $lastKnownMap->id,
        'player_id' => $player->id,
        'created_at' => now()->subHour(),
    ]);

    $row = collect(Livewire::test(ServerList::class)->get('servers'))->firstWhere('id', $server->id);

    expect($row['online'])->toBeFalse()
        ->and($row['map'])->toBe('Last Known Map');
});

it('falls back to the lap-history proxy once live data goes stale', function () {
    $liveMap = Map::factory()->create(['label' => 'Old Live Map']);
    $recentMap = Map::factory()->create(['label' => 'Recent Proxy Map']);
    $player = Player::factory()->create();

    $server = Server::factory()->create([
        'current_map_id' => $liveMap->id,
        'queried_at' => now()->subHours(2),
        'query_successful' => true,
    ]);

    LapTime::factory()->create([
        'server_id' => $server->id,
        'map_id' => $recentMap->id,
        'player_id' => $player->id,
        'created_at' => now()->subHour(),
    ]);

    $row = collect(Livewire::test(ServerList::class)->get('servers'))->firstWhere('id', $server->id);

    expect($row['online'])->toBeTrue()
        ->and($row['map'])->toBe('Recent Proxy Map');
});

// Roadmap item 16 follow-up — every submitted lap changes header stats/Activity Score, not just
// a PB, so this listens on the site-wide `activity` channel rather than a map-scoped one. The
// listener itself is what runs when the browser's Echo client receives the event; there's no
// running WebSocket server in Pest to exercise the real transport (see decisions.md).
it('re-fetches server data when its live-update listener fires', function () {
    // At least one server must exist up front — an empty server list is a separate, pre-existing
    // edge case unrelated to this test (the featured-card view assumes at least one row).
    $server = Server::factory()->create();

    $component = Livewire::test(ServerList::class);
    expect($component->get('totalPlayers'))->toBe(0);

    $map = Map::factory()->create();
    $player = Player::factory()->create();
    LapTime::factory()->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $player->id]);

    $component->call('loadServers');

    expect($component->get('totalPlayers'))->toBe(1);
});
