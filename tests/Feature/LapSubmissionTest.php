<?php

use App\Events\LapSubmitted;
use App\Events\LeaderboardUpdated;
use App\Helpers\GameServerQuery;
use App\Models\LapTime;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

it('broadcasts LapSubmitted on every attempt, improvement or not', function () {
    fakeGameServerQuery();
    Event::fake([LapSubmitted::class]);

    submitLap(['player_time' => 50]);
    submitLap(['player_time' => 55]);

    Event::assertDispatchedTimes(LapSubmitted::class, 2);
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

// SEC-01 (docs/security.md) — HRL query verification. `enforce` defaults to false so these
// don't need touching every other test above: an unverified submission still gets recorded,
// just logged, since real game servers won't all have an updated Lua script on day one.
it('still records the lap when HRL verification fails but enforcement is off (the default)', function () {
    config(['webhook.hrl_query.enforce' => false]);
    fakeGameServerQuery(['hostname' => 'Legacy Server', 'numplayers' => '1']); // no hrl_* fields

    submitLap()->assertOk()->assertJson(['success' => true]);

    expect(LapTime::count())->toBe(1);
});

it('requires a submission_id once HRL enforcement is on', function () {
    config(['webhook.hrl_query.enforce' => true]);
    fakeGameServerQuery();

    submitLap(['hrl_token' => 'secret-token'])->assertUnprocessable();
});

it('rejects a submission that fails HRL verification once enforcement is on', function () {
    config(['webhook.hrl_query.enforce' => true]);
    fakeGameServerQuery(['hostname' => 'Legacy Server', 'numplayers' => '1']); // no hrl_* fields

    submitLap(['submission_id' => 'enforced-001'])
        ->assertStatus(403)
        ->assertJson(['success' => false, 'reason' => 'missing_hrl_marker']);

    expect(LapTime::count())->toBe(0);
});

it('accepts a submission that matches a live, HRL-enabled query response under enforcement', function () {
    config(['webhook.hrl_query.enforce' => true]);
    fakeGameServerQuery([
        'hostname' => 'Real Halo Server',
        'hrl_enabled' => '1',
        'hrl_protocol' => '1',
        'hrl_token' => 'secret-token',
        'mapname' => 'bloodgulch',
        'player_0' => 'Effakt',
    ]);

    submitLap(['hrl_token' => 'secret-token', 'submission_id' => 'enforced-002'])->assertOk()->assertJson(['success' => true]);

    expect(LapTime::count())->toBe(1);
});

it('replays the original response for an exact duplicate submission, without recording it twice', function () {
    fakeGameServerQuery();

    $first = submitLap()->assertOk()->json();
    $second = submitLap()->assertOk()->json();

    expect($second)->toBe($first);
    expect(LapTime::count())->toBe(1);
});

it('replays the original response for a genuine retry with a repeated submission_id', function () {
    fakeGameServerQuery();

    $first = submitLap(['submission_id' => 'retry-00001'])->assertOk()->json();
    // Identical payload, same submission_id — a real Lua-side retry after a lost HTTP response.
    $second = submitLap(['submission_id' => 'retry-00001'])->assertOk()->json();

    expect($second)->toBe($first);
    expect(LapTime::count())->toBe(1);
});

it('rejects a reused submission_id whose lap content has actually changed (SEC-01 audit follow-up)', function () {
    fakeGameServerQuery();

    submitLap(['submission_id' => 'retry-00001'])->assertOk();
    // Same submission_id, but a materially different lap (a bug or a colliding ID, not a
    // legitimate retry) — silently replaying the first response would hide that this second
    // attempt was never actually recorded.
    submitLap(['submission_id' => 'retry-00001', 'player_time' => 99])
        ->assertStatus(409)
        ->assertJson(['success' => false, 'reason' => 'idempotency_conflict']);

    expect(LapTime::count())->toBe(1);
});

it('does not let two different servers collide on a similar/identical submission_id', function () {
    fakeGameServerQuery();

    submitLap(['port' => 2302, 'submission_id' => 'counter-1'])->assertOk();
    submitLap(['port' => 2303, 'submission_id' => 'counter-1'])->assertOk();

    // Both recorded as separate laps on separate servers — the idempotency key (both the cache
    // guard and the durable DB constraint) is namespaced by ip:port, so the same client-supplied
    // submission_id from two different servers never collides.
    expect(LapTime::count())->toBe(2);
    expect(Server::count())->toBe(2);
});

it('reports a genuine concurrent duplicate as 409 without ever completing processing', function () {
    fakeGameServerQuery();

    $contentHash = hash('sha256', json_encode(['abc123', 'bloodgulch', 42.5, null, null]));
    Cache::put(
        "lap-submission:127.0.0.1:2302:{$contentHash}",
        ['status' => 'processing', 'contentHash' => $contentHash],
        now()->addSeconds(10),
    );

    submitLap()
        ->assertStatus(409)
        ->assertJson(['success' => false, 'reason' => 'duplicate_submission']);

    expect(LapTime::count())->toBe(0);
});

it('releases the idempotency reservation on a pre-commit failure so a real retry is not stuck', function () {
    app()->bind(GameServerQuery::class, fn () => new class implements GameServerQuery
    {
        public function query(string $ip, int $port, int $timeoutSeconds = 2): array|false
        {
            throw new RuntimeException('simulated pre-commit failure');
        }

        public function getError(): ?string
        {
            return null;
        }
    });

    submitLap(['submission_id' => 'retry-00002'])->assertServerError();

    fakeGameServerQuery();
    submitLap(['submission_id' => 'retry-00002'])->assertOk()->assertJson(['success' => true]);

    expect(LapTime::count())->toBe(1);
});

it('durably prevents a duplicate lap row even if the idempotency cache entry is gone', function () {
    fakeGameServerQuery();

    submitLap(['submission_id' => 'durable-0001'])->assertOk();
    // Simulates the cache entry being lost (restart, eviction, a very late retry) — the
    // (server_id, submission_id) unique DB constraint is what actually prevents a second
    // lap_times row in that case, not the cache.
    Cache::flush();

    submitLap(['submission_id' => 'durable-0001'])->assertOk()->assertJson(['success' => true]);

    expect(LapTime::count())->toBe(1);
});

it('rate-limits by IP even when the caller rotates the port on every request', function () {
    config([
        'webhook.rate_limit.unverified.per_ip_per_minute' => 2,
        'webhook.rate_limit.unverified.per_ip_port_per_minute' => 100,
    ]);
    fakeGameServerQuery();

    submitLap(['port' => 1001])->assertOk();
    submitLap(['port' => 1002])->assertOk();
    submitLap(['port' => 1003])->assertStatus(429);
});

it('rate-limits by ip:port independently of the coarser per-IP ceiling', function () {
    config([
        'webhook.rate_limit.unverified.per_ip_per_minute' => 1000,
        'webhook.rate_limit.unverified.per_ip_port_per_minute' => 2,
    ]);
    fakeGameServerQuery();

    submitLap(['port' => 2302, 'player_hash' => 'p1'])->assertOk();
    submitLap(['port' => 2302, 'player_hash' => 'p2'])->assertOk();
    submitLap(['port' => 2302, 'player_hash' => 'p3'])->assertStatus(429);
});

it('grants the more generous verified tier only after a request from that ip:port has actually verified', function () {
    config([
        'webhook.rate_limit.unverified.per_ip_port_per_minute' => 1,
        'webhook.rate_limit.verified.per_ip_port_per_minute' => 100,
    ]);

    // The FIRST request from this ip:port is itself still charged against the strict
    // "unverified" tier (the marker it sets only helps requests AFTER this one) — there's
    // exactly enough of that tier's 1/min allowance for this single request to succeed.
    fakeGameServerQuery([
        'hostname' => 'Real Halo Server',
        'hrl_enabled' => '1',
        'hrl_protocol' => '1',
        'hrl_token' => 'secret-token',
        'mapname' => 'bloodgulch',
        'player_0' => 'Effakt',
    ]);
    submitLap(['player_hash' => 'p1', 'hrl_token' => 'secret-token'])->assertOk();

    // A second and third request from the SAME ip:port would have been blocked outright under
    // the unverified ceiling of 1/min — they succeed here specifically because the first
    // request's passing verification marked this source as verified for the limiter's benefit.
    submitLap(['player_hash' => 'p2', 'hrl_token' => 'secret-token'])->assertOk();
    submitLap(['player_hash' => 'p3', 'hrl_token' => 'secret-token'])->assertOk();
});

it('revokes the verified rate-limit marker immediately on a failed verification, not just lets it expire', function () {
    config([
        'webhook.hrl_query.enforce' => false,
        'webhook.rate_limit.unverified.per_ip_port_per_minute' => 1,
        'webhook.rate_limit.verified.per_ip_port_per_minute' => 100,
    ]);

    // First request passes real verification and earns the generous "verified" tier.
    fakeGameServerQuery([
        'hostname' => 'Real Halo Server',
        'hrl_enabled' => '1',
        'hrl_protocol' => '1',
        'hrl_token' => 'secret-token',
        'mapname' => 'bloodgulch',
        'player_0' => 'Effakt',
    ]);
    submitLap(['player_hash' => 'p1', 'hrl_token' => 'secret-token'])->assertOk();

    // Second request from the same ip:port fails verification (the "server" stopped answering
    // HRL fields) — enforcement is off, so it's still recorded, but the verified marker should
    // be revoked as part of processing this failure, not left to expire on its own 5-minute TTL.
    fakeGameServerQuery(['hostname' => 'Legacy Server', 'numplayers' => '1']);
    submitLap(['player_hash' => 'p2'])->assertOk();

    // A third request immediately exceeds the strict unverified ceiling of 1/min (2 requests
    // already counted against it) — proving the marker was actually gone, not still granting
    // the 100/min verified allowance it would take for this to succeed.
    submitLap(['player_hash' => 'p3'])->assertStatus(429);
});

it('rejects a duplicate (ip, port) at the database level, even for an already-soft-deleted server', function () {
    // Schema-level proof for the servers.(ip, port, deleted_at) unique constraint (SEC-01 audit
    // follow-up) — the real concurrent-request race it guards against (two simultaneous
    // first-ever submissions for a brand-new ip:port both passing Server::firstOrCreate()'s
    // SELECT before either INSERT commits) isn't reproducible in a single-threaded test, but the
    // constraint itself, and that it doesn't wrongly block a *soft-deleted* server's old
    // ip:port from being reused, are.
    Server::factory()->create(['ip' => '10.0.0.1', 'port' => '2302']);

    expect(fn () => Server::factory()->create(['ip' => '10.0.0.1', 'port' => '2302']))
        ->toThrow(UniqueConstraintViolationException::class);

    $archived = Server::factory()->create(['ip' => '10.0.0.2', 'port' => '2302']);
    $archived->delete();

    // A genuinely new server reusing an archived one's old (ip, port) is NOT blocked — the
    // constraint includes deleted_at specifically so this stays possible.
    expect(fn () => Server::factory()->create(['ip' => '10.0.0.2', 'port' => '2302']))->not->toThrow(Throwable::class);
});

it('does not query the game server a second time when HRL verification already fetched a live response', function () {
    config(['webhook.hrl_query.enforce' => true]);

    $query = new class implements GameServerQuery
    {
        public int $calls = 0;

        public function query(string $ip, int $port, int $timeoutSeconds = 2): array|false
        {
            $this->calls++;

            return [
                'hostname' => 'Real Halo Server',
                'hrl_enabled' => '1',
                'hrl_protocol' => '1',
                'hrl_token' => 'secret-token',
                'mapname' => 'bloodgulch',
                'player_0' => 'Effakt',
            ];
        }

        public function getError(): ?string
        {
            return null;
        }
    };
    app()->bind(GameServerQuery::class, fn () => $query);

    submitLap(['hrl_token' => 'secret-token', 'submission_id' => 'enforced-003'])->assertOk();

    expect(Server::sole()->name)->toBe('Real Halo Server');
    expect($query->calls)->toBe(1);
});
