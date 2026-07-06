<?php

use App\Events\LeaderboardUpdated;
use App\Helpers\GameServerQuery;
use App\Models\LapTime;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

uses(LazilyRefreshDatabase::class);

function fakeGameServerQuery(array|false $response = ['hostname' => 'Live Server Name', 'numplayers' => '1']): void
{
    app()->bind(GameServerQuery::class, fn () => new class($response) implements GameServerQuery
    {
        public function __construct(private readonly array|false $response) {}

        public function query(string $ip, int $port, int $timeoutSeconds = 2): array|false
        {
            return $this->response;
        }

        public function getError(): ?string
        {
            return $this->response === false ? 'stubbed failure' : null;
        }
    });
}

function submitLap(array $overrides = []): TestResponse
{
    return test()->postJson('/api/v1/laps', array_merge([
        'map_name' => 'bloodgulch',
        'player_hash' => 'abc123',
        'player_name' => 'Effakt',
        'player_time' => 42.5,
        'port' => 2302,
        'race_type' => 0,
    ], $overrides));
}

it('creates the server, player, and map on a first submission, live-querying the hostname', function () {
    fakeGameServerQuery(['hostname' => 'Real Halo Server', 'numplayers' => '0']);

    submitLap()
        ->assertOk()
        ->assertJson(['success' => true, 'isNewRecord' => true]);

    $server = Server::sole();
    expect($server->name)->toBe('Real Halo Server');
    expect(Player::sole()->hash)->toBe(hash('sha256', 'abc123'));
    $map = Map::sole();
    expect($map->name)->toBe('bloodgulch');
    expect($map->label)->toBe('Bloodgulch');
    expect(LapTime::sole()->time)->toEqual(42.5);
});

it('derives the map label from the alias dictionary plus a race-type suffix', function () {
    fakeGameServerQuery();

    submitLap(['map_name' => 'bloodgulch', 'race_type' => 1])->assertOk();

    expect(Map::sole()->label)->toBe('Bloodgulch - Any Order');
});

it('falls back to a placeholder server name when the live query fails, without dropping the lap', function () {
    fakeGameServerQuery(false);

    submitLap()->assertOk()->assertJson(['success' => true]);

    expect(Server::sole()->name)->toContain('Unknown (');
    expect(LapTime::count())->toBe(1);
});

it('logs every attempt now, not only personal-best improvements', function () {
    fakeGameServerQuery();

    submitLap(['player_time' => 50]);
    $response = submitLap(['player_time' => 55]);

    $response->assertOk()->assertJson(['isNewRecord' => false]);
    expect(LapTime::count())->toBe(2);
});

it('does not duplicate players_servers/servers_maps pivot rows across repeated submissions', function () {
    fakeGameServerQuery();

    submitLap(['player_time' => 50]);
    submitLap(['player_time' => 45]);

    $server = Server::sole();
    expect($server->players()->count())->toBe(1);
    expect($server->maps()->count())->toBe(1);
});

it('stores splits alongside the lap', function () {
    fakeGameServerQuery();

    submitLap([
        'splits' => [
            ['checkpoint_id' => 1, 'duration' => 10.5, 'startTime' => 0, 'endTime' => 10.5],
            ['checkpoint_id' => 2, 'duration' => 12.0, 'startTime' => 10.5, 'endTime' => 22.5],
        ],
    ])->assertOk();

    expect(LapTime::sole()->splits)->toHaveCount(2);
});

it('broadcasts LeaderboardUpdated only when the lap is a genuine improvement', function () {
    fakeGameServerQuery();
    Event::fake([LeaderboardUpdated::class]);

    submitLap(['player_time' => 50]);
    Event::assertDispatched(LeaderboardUpdated::class);

    submitLap(['player_time' => 55]);
    Event::assertDispatchedTimes(LeaderboardUpdated::class, 1);
});

it('reports the correct leaderboard position and gap to the top time', function () {
    fakeGameServerQuery();

    submitLap(['player_hash' => 'p1', 'player_time' => 40]);

    $response = submitLap(['player_hash' => 'p2', 'player_time' => 45]);

    $response->assertOk()
        ->assertJsonPath('leaderboardPosition.position', 2)
        ->assertJsonPath('leaderboardPosition.total', 2)
        ->assertJsonPath('leaderboardPosition.topTime', 40)
        ->assertJsonPath('leaderboardPosition.difference', 5);
});

it('validates the payload', function () {
    fakeGameServerQuery();

    submitLap(['player_time' => 'not-a-number'])->assertUnprocessable();
    submitLap(['map_name' => null])->assertUnprocessable();
});
