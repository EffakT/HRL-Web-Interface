<?php

use App\Helpers\GameServerQuery;
use App\Models\Map;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function fakeGameServerQueryFor(array $responsesByServerId): void
{
    app()->bind(GameServerQuery::class, fn () => new class($responsesByServerId) implements GameServerQuery
    {
        public function __construct(private readonly array $responsesByServerId) {}

        public function query(string $ip, int $port, int $timeoutSeconds = 2): array|false
        {
            return $this->responsesByServerId["{$ip}:{$port}"] ?? false;
        }

        public function getError(): ?string
        {
            return 'stubbed failure';
        }
    });
}

it('stores the live-queried map and player count against the server row', function () {
    $map = Map::factory()->create(['name' => 'bloodgulch']);
    $server = Server::factory()->create(['ip' => '10.0.0.1', 'port' => '2302']);

    fakeGameServerQueryFor([
        '10.0.0.1:2302' => ['hostname' => 'Live Server', 'mapname' => 'bloodgulch', 'numplayers' => '3'],
    ]);

    $this->artisan('app:refresh-live-server-info')->assertSuccessful();

    $server->refresh();
    expect($server->current_map_id)->toBe($map->id)
        ->and($server->live_player_count)->toBe(3)
        ->and($server->query_successful)->toBeTrue()
        ->and($server->queried_at)->not->toBeNull();
});

it('does not fabricate a Map row for an unrecognized live mapname', function () {
    $server = Server::factory()->create(['ip' => '10.0.0.2', 'port' => '2302']);

    fakeGameServerQueryFor([
        '10.0.0.2:2302' => ['hostname' => 'Live Server', 'mapname' => 'some_unknown_map', 'numplayers' => '1'],
    ]);

    $this->artisan('app:refresh-live-server-info')->assertSuccessful();

    expect(Map::count())->toBe(0)
        ->and($server->refresh()->current_map_id)->toBeNull();
});

it('marks a failed query without wiping previously known live data', function () {
    $map = Map::factory()->create();
    $server = Server::factory()->create([
        'ip' => '10.0.0.3',
        'port' => '2302',
        'current_map_id' => $map->id,
        'live_player_count' => 5,
        'query_successful' => true,
    ]);

    fakeGameServerQueryFor([]); // no entry for this ip:port -> query() returns false

    $this->artisan('app:refresh-live-server-info')->assertSuccessful();

    $server->refresh();
    expect($server->query_successful)->toBeFalse()
        ->and($server->queried_at)->not->toBeNull()
        ->and($server->current_map_id)->toBe($map->id)
        ->and($server->live_player_count)->toBe(5);
});

it('skips archived servers', function () {
    $server = Server::factory()->create(['ip' => '10.0.0.4', 'port' => '2302']);
    $server->delete();

    fakeGameServerQueryFor([
        '10.0.0.4:2302' => ['hostname' => 'Should not be queried', 'mapname' => 'bloodgulch', 'numplayers' => '0'],
    ]);

    $this->artisan('app:refresh-live-server-info')->assertSuccessful();

    expect($server->fresh()->queried_at)->toBeNull();
});
