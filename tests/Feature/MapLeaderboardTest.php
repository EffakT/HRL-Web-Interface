<?php

use App\Livewire\Maps\MapLeaderboard;
use App\Models\LapTime;
use App\Models\LapTimeSplit;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

it('ranks each player by their global best lap across every server', function () {
    $map = Map::factory()->create(['label' => 'Global Test Map']);
    $otherMap = Map::factory()->create();
    $serverA = Server::factory()->create(['name' => 'Server Alpha']);
    $serverB = Server::factory()->create(['name' => 'Server Beta']);
    $playerA = Player::factory()->create(['name' => 'Global Leader']);
    $playerB = Player::factory()->create(['name' => 'Global Runner Up']);

    LapTime::factory()->create([
        'map_id' => $map->id,
        'server_id' => $serverA->id,
        'player_id' => $playerA->id,
        'time' => 70,
    ]);
    LapTime::factory()->create([
        'map_id' => $map->id,
        'server_id' => $serverB->id,
        'player_id' => $playerA->id,
        'time' => 60,
    ]);
    LapTime::factory()->create([
        'map_id' => $map->id,
        'server_id' => $serverA->id,
        'player_id' => $playerB->id,
        'time' => 65,
    ]);
    LapTime::factory()->create([
        'map_id' => $otherMap->id,
        'server_id' => $serverA->id,
        'player_id' => $playerB->id,
        'time' => 40,
    ]);

    $component = Livewire::test(MapLeaderboard::class, ['mapId' => (string) $map->id]);
    $players = $component->get('players');

    expect($component->get('map'))->toBe('Global Test Map')
        ->and($component->get('totalLaps'))->toBe(3)
        ->and($players)->toHaveCount(2)
        ->and($players[0]['name'])->toBe('Global Leader')
        ->and($players[0]['time'])->toBe('1:00.00')
        ->and($players[0]['subtitle'])->toBe('Server Beta')
        ->and($players[1]['name'])->toBe('Global Runner Up');

    $response = $this->get("/maps/{$map->id}")
        ->assertSee('Global Test Map')
        ->assertSee('Server Beta')
        ->assertSee(route('servers.show', ['serverId' => $serverB->id]));

    // One empty-state message is in the #1 podium and one is in its always-rendered modal.
    expect(substr_count($response->getContent(), 'No split data available for this lap.'))->toBe(2);
});

it('breaks equal-time global positions by earliest achievement date', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();
    $laterPlayer = Player::factory()->create(['name' => 'Later Player']);
    $earlierPlayer = Player::factory()->create(['name' => 'Earlier Player']);

    // Create the later lap first so id order cannot accidentally make this test pass.
    LapTime::factory()->create([
        'map_id' => $map->id,
        'server_id' => $server->id,
        'player_id' => $laterPlayer->id,
        'time' => 60,
        'created_at' => '2026-01-02 00:00:00',
    ]);
    LapTime::factory()->create([
        'map_id' => $map->id,
        'server_id' => $server->id,
        'player_id' => $earlierPlayer->id,
        'time' => 60,
        'created_at' => '2026-01-01 00:00:00',
    ]);

    $players = Livewire::test(MapLeaderboard::class, ['mapId' => (string) $map->id])->get('players');

    expect($players[0]['name'])->toBe('Earlier Player')
        ->and($players[1]['name'])->toBe('Later Player');
});

it('excludes laps from archived servers', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create(['name' => 'Archived Race Server']);
    $player = Player::factory()->create();

    LapTime::factory()->create([
        'map_id' => $map->id,
        'server_id' => $server->id,
        'player_id' => $player->id,
        'time' => 60,
    ]);
    $server->delete();

    $component = Livewire::test(MapLeaderboard::class, ['mapId' => (string) $map->id]);

    expect($component->get('players'))->toBe([])
        ->and($component->get('totalLaps'))->toBe(0);
});

it('compares the global record lap against the runner-up using real splits', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();
    $recordPlayer = Player::factory()->create(['name' => 'Record Player']);
    $runnerUpPlayer = Player::factory()->create(['name' => 'Runner Up Player']);
    $record = LapTime::factory()->create([
        'map_id' => $map->id,
        'server_id' => $server->id,
        'player_id' => $recordPlayer->id,
        'time' => 60,
    ]);
    $runnerUp = LapTime::factory()->create([
        'map_id' => $map->id,
        'server_id' => $server->id,
        'player_id' => $runnerUpPlayer->id,
        'time' => 61,
    ]);

    LapTimeSplit::factory()->create(['lap_time_id' => $record->id, 'checkpoint_id' => 1, 'duration' => 5]);
    LapTimeSplit::factory()->create(['lap_time_id' => $runnerUp->id, 'checkpoint_id' => 1, 'duration' => 5.5]);

    $component = Livewire::test(MapLeaderboard::class, ['mapId' => (string) $map->id])
        ->call('openLap', 0);

    $reference = $component->get('comparisonReference');
    $comparison = $component->get('comparison');

    expect($reference['label'])->toBe('RUNNER-UP')
        ->and($reference['name'])->toBe('Runner Up Player')
        ->and($comparison)->toHaveCount(1)
        ->and($comparison[0]['myTime'])->toBe('5.000')
        ->and($comparison[0]['refTime'])->toBe('5.500')
        ->and($comparison[0]['faster'])->toBeTrue();
});

it('paginates ranks after the fixed top-three podium', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create();

    foreach (range(1, 20) as $rank) {
        $player = Player::factory()->create(['name' => 'Driver '.str_pad((string) $rank, 2, '0', STR_PAD_LEFT)]);

        LapTime::factory()->create([
            'map_id' => $map->id,
            'server_id' => $server->id,
            'player_id' => $player->id,
            'time' => 59 + $rank,
        ]);
    }

    $component = Livewire::test(MapLeaderboard::class, ['mapId' => (string) $map->id]);
    $pageOne = $component->viewData('rankedPlayers');

    expect($pageOne->currentPage())->toBe(1)
        ->and($pageOne->total())->toBe(17)
        ->and($pageOne->count())->toBe(15)
        ->and($pageOne->first()['rank'])->toBe('04')
        ->and($pageOne->last()['rank'])->toBe('18');

    $component->call('nextPage', 'page');
    $pageTwo = $component->viewData('rankedPlayers');

    expect($pageTwo->currentPage())->toBe(2)
        ->and($pageTwo->count())->toBe(2)
        ->and($pageTwo->first()['rank'])->toBe('19')
        ->and($pageTwo->last()['rank'])->toBe('20')
        ->and($component->get('selectedPlayerIndex'))->toBeNull();
});
