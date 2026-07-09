<?php

use App\Events\LapSubmitted;
use App\Events\LeaderboardUpdated;
use App\Helpers\GameServerQuery;
use App\Jobs\ProcessNewLap;
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

it('derives the map label from the alias dictionary plus a race-type suffix, and its own map identity', function () {
    fakeGameServerQuery();

    submitLap(['map_name' => 'bloodgulch', 'race_type' => 1])->assertOk();

    $map = Map::sole();
    // race_type has its own Map identity (2026-07-08 follow-up, docs/decisions.md), not just a
    // label suffix on the same row as a normal-race lap — see the tests below for the fork
    // itself.
    expect($map->name)->toBe('bloodgulch-anyorder');
    expect($map->label)->toBe('Bloodgulch - Any Order');
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

it('rejects splits with a duplicate checkpoint_id (SEC-01 audit follow-up)', function () {
    fakeGameServerQuery();

    submitLap([
        'splits' => [
            ['checkpoint_id' => 1, 'duration' => 10.5, 'startTime' => 0, 'endTime' => 10.5],
            ['checkpoint_id' => 1, 'duration' => 11.0, 'startTime' => 0, 'endTime' => 11.0],
        ],
    ])->assertUnprocessable();

    expect(LapTime::count())->toBe(0);
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
        ->assertJsonPath('leaderboardPosition.top_time', 40)
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

it('rejects a reused submission_id whose content has changed even after the cache entry is gone (SEC-01 audit follow-up)', function () {
    fakeGameServerQuery();

    submitLap(['submission_id' => 'retry-00003'])->assertOk();

    // Simulate the cache-based idempotency guard's copy having expired/been evicted/lost to a
    // restart — the only remaining source of truth is the durable submission_hash stored on
    // the recorded lap itself.
    Cache::flush();

    submitLap(['submission_id' => 'retry-00003', 'player_time' => 77])
        ->assertStatus(409)
        ->assertJson(['success' => false, 'reason' => 'idempotency_conflict']);

    expect(LapTime::count())->toBe(1);
});

it('replays a genuine retry with a repeated submission_id even after the cache entry is gone', function () {
    fakeGameServerQuery();

    submitLap(['submission_id' => 'retry-00004'])->assertOk();
    Cache::flush();

    submitLap(['submission_id' => 'retry-00004'])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(LapTime::count())->toBe(1);
});

it('classifies unique-constraint violations by their actual index/columns, not by elimination', function () {
    $submissionIdViolation = new UniqueConstraintViolationException(
        'mysql', 'insert into `lap_times` ...', [],
        new PDOException("SQLSTATE[23000]: ... 'lap_times_server_id_submission_id_unique'"),
    );
    $submissionIdViolation->columns = ['submission_id'];
    $submissionIdViolation->index = 'lap_times_server_id_submission_id_unique';

    $serverIdentityViolation = new UniqueConstraintViolationException(
        'mysql', 'insert into `servers` ...', [],
        new PDOException("SQLSTATE[23000]: ... 'servers_ip_port_active_since_unique'"),
    );
    $serverIdentityViolation->columns = ['ip', 'port', 'active_since'];
    $serverIdentityViolation->index = 'servers_ip_port_active_since_unique';

    $unrelatedViolation = new UniqueConstraintViolationException(
        'mysql', 'insert into `something_else` ...', [],
        new PDOException("SQLSTATE[23000]: ... 'something_else_unique'"),
    );
    $unrelatedViolation->columns = ['some_other_column'];
    $unrelatedViolation->index = 'something_else_unique';

    $job = new ProcessNewLap(ip: '127.0.0.1', port: 2302, data: []);
    $violatesSubmissionId = (new ReflectionMethod($job, 'violatesSubmissionIdUniqueness'))->getClosure($job);
    $violatesServerIdentity = (new ReflectionMethod($job, 'violatesServerIdentityUniqueness'))->getClosure($job);

    expect($violatesSubmissionId($submissionIdViolation))->toBeTrue();
    expect($violatesServerIdentity($submissionIdViolation))->toBeFalse();

    expect($violatesSubmissionId($serverIdentityViolation))->toBeFalse();
    expect($violatesServerIdentity($serverIdentityViolation))->toBeTrue();

    expect($violatesSubmissionId($unrelatedViolation))->toBeFalse();
    expect($violatesServerIdentity($unrelatedViolation))->toBeFalse();
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

    $contentHash = hash('sha256', json_encode(['abc123', 'Effakt', 'bloodgulch', 0, 42.5, null, []]));
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

it('rewrites a known internal NAT ip to the real public ip before recording the server (ported from legacy)', function () {
    config(['webhook.internal_ip_map' => ['192.168.88.1' => '114.23.254.181']]);
    fakeGameServerQuery();

    test()->withServerVariables(['REMOTE_ADDR' => '192.168.88.1']);
    submitLap()->assertOk();

    expect(Server::sole()->ip)->toBe('114.23.254.181');
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

// SEC-04 audit follow-up (docs/security.md) — a map's checkpoint layout is learned from the
// first split-bearing submission and enforced after that; a mismatch forks a new map identity
// rather than corrupting the original or rejecting the lap. `enforce` explicitly disabled in
// each test below, independent of whatever the real .env currently has it set to, so these
// don't need HRL-verification fields just to isolate checkpoint-count behavior.
function splitsWithCheckpoints(int $count): array
{
    return collect(range(1, $count))
        ->map(fn (int $checkpointId): array => [
            'checkpoint_id' => $checkpointId,
            'duration' => 10.0,
            'startTime' => ($checkpointId - 1) * 10.0,
            'endTime' => $checkpointId * 10.0,
        ])
        ->all();
}

it('learns a map\'s checkpoint count from its first split-bearing submission', function () {
    config(['webhook.hrl_query.enforce' => false]);
    fakeGameServerQuery();

    submitLap(['splits' => splitsWithCheckpoints(5)])->assertOk();

    expect(Map::sole())
        ->name->toBe('bloodgulch')
        ->checkpoint_count->toBe(5);
});

it('reuses the same map identity when a later submission matches the learned checkpoint count', function () {
    config(['webhook.hrl_query.enforce' => false]);
    fakeGameServerQuery();

    submitLap(['splits' => splitsWithCheckpoints(5)])->assertOk();
    submitLap(['splits' => splitsWithCheckpoints(5), 'player_time' => 40])->assertOk();

    expect(Map::count())->toBe(1);
    expect(LapTime::count())->toBe(2);
});

it('forks a new map identity when a submission\'s checkpoint count differs from the learned baseline', function () {
    config(['webhook.hrl_query.enforce' => false]);
    fakeGameServerQuery();

    submitLap(['splits' => splitsWithCheckpoints(5)])->assertOk();
    submitLap(['splits' => splitsWithCheckpoints(6), 'player_time' => 40])->assertOk();

    expect(Map::count())->toBe(2);
    $original = Map::where('name', 'bloodgulch')->sole();
    $variant = Map::where('name', 'bloodgulch-splits-6')->sole();

    expect($original->checkpoint_count)->toBe(5)
        ->and($variant->checkpoint_count)->toBe(6)
        ->and($variant->label)->toBe('Bloodgulch (6 CP)');

    // The variant's own lap is recorded against it, not the original.
    expect(LapTime::where('map_id', $variant->id)->count())->toBe(1);
    expect(LapTime::where('map_id', $original->id)->count())->toBe(1);
});

it('never establishes or checks a checkpoint baseline for a splitless submission', function () {
    config(['webhook.hrl_query.enforce' => false]);
    fakeGameServerQuery();

    // Establish a baseline, then submit a splitless lap (the common real-world case) — it must
    // use the same map, not fork, and must not disturb the learned baseline.
    submitLap(['splits' => splitsWithCheckpoints(5)])->assertOk();
    submitLap(['player_time' => 40])->assertOk();

    expect(Map::count())->toBe(1);
    expect(Map::sole()->checkpoint_count)->toBe(5);
    expect(LapTime::count())->toBe(2);
});

it('rejects a submission claiming more checkpoints than the configured protocol-wide ceiling', function () {
    config(['webhook.hrl_query.enforce' => false, 'webhook.max_checkpoints' => 20]);
    fakeGameServerQuery();

    submitLap(['splits' => splitsWithCheckpoints(21)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['splits']);

    expect(LapTime::count())->toBe(0);
});

// SEC-04 audit follow-up, numeric-range portion (docs/security.md) — player_time upper bound,
// splits.*.duration bound relative to it, and a loose startTime/endTime overflow guard that
// deliberately doesn't reject the large absolute-clock-like values real submissions actually use.
it('rejects a player_time beyond the configured ceiling', function () {
    config(['webhook.hrl_query.enforce' => false, 'webhook.max_lap_time_seconds' => 3600]);
    fakeGameServerQuery();

    submitLap(['player_time' => 3601])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['player_time']);

    expect(LapTime::count())->toBe(0);
});

it('accepts a player_time exactly at the configured ceiling', function () {
    config(['webhook.hrl_query.enforce' => false, 'webhook.max_lap_time_seconds' => 3600]);
    fakeGameServerQuery();

    submitLap(['player_time' => 3600])->assertOk();
});

it('rejects a split duration longer than the lap\'s own player_time', function () {
    config(['webhook.hrl_query.enforce' => false]);
    fakeGameServerQuery();

    submitLap([
        'player_time' => 42.5,
        'splits' => [
            ['checkpoint_id' => 1, 'duration' => 50.0, 'startTime' => 0, 'endTime' => 50.0],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['splits.0.duration']);

    expect(LapTime::count())->toBe(0);
});

it('accepts the large absolute-clock-like startTime/endTime values real submissions actually send', function () {
    config(['webhook.hrl_query.enforce' => false]);
    fakeGameServerQuery();

    // Real data includes exactly this kind of value (up to a literal 999999.99 sentinel) —
    // these fields aren't reliably lap-relative across Lua script versions in the wild, so they
    // must stay accepted rather than being rejected by an over-tight bound.
    submitLap([
        'splits' => [
            ['checkpoint_id' => 1, 'duration' => 10.5, 'startTime' => 993881.17, 'endTime' => 999999.99],
        ],
    ])->assertOk();
});

it('rejects a negative startTime/endTime', function () {
    config(['webhook.hrl_query.enforce' => false]);
    fakeGameServerQuery();

    submitLap([
        'splits' => [
            ['checkpoint_id' => 1, 'duration' => 10.5, 'startTime' => -1, 'endTime' => 10.5],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['splits.0.startTime']);
});

it('rejects a startTime/endTime beyond the overflow guard', function () {
    config(['webhook.hrl_query.enforce' => false]);
    fakeGameServerQuery();

    submitLap([
        'splits' => [
            ['checkpoint_id' => 1, 'duration' => 10.5, 'startTime' => 0, 'endTime' => 100000000],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['splits.0.endTime']);
});

// SEC-04 review follow-up (docs/security.md) — a second security review of the SEC-04 fix found
// `splits.*.duration` had no lower bound and `splits.*.checkpoint_id`'s `distinct` rule alone
// let any N distinct values through as a "valid" N-checkpoint layout (not just the map's real
// contiguous 1..N sequence), plus a genuine concurrency gap in how a map's checkpoint-count
// baseline gets established and how variant map identities get created.
it('rejects a zero or negative split duration', function () {
    config(['webhook.hrl_query.enforce' => false]);
    fakeGameServerQuery();

    submitLap([
        'splits' => [
            ['checkpoint_id' => 1, 'duration' => 0, 'startTime' => 0, 'endTime' => 0],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['splits.0.duration']);

    submitLap([
        'splits' => [
            ['checkpoint_id' => 1, 'duration' => -5.0, 'startTime' => 0, 'endTime' => 0],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['splits.0.duration']);

    expect(LapTime::count())->toBe(0);
});

it('rejects checkpoint IDs that are merely distinct, not the real contiguous 1..N sequence', function () {
    config(['webhook.hrl_query.enforce' => false]);
    fakeGameServerQuery();

    submitLap([
        'splits' => [
            ['checkpoint_id' => 1, 'duration' => 10.0, 'startTime' => 0, 'endTime' => 10.0],
            ['checkpoint_id' => 2, 'duration' => 10.0, 'startTime' => 10.0, 'endTime' => 20.0],
            ['checkpoint_id' => 4, 'duration' => 10.0, 'startTime' => 20.0, 'endTime' => 30.0],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['splits']);

    submitLap([
        'splits' => [
            ['checkpoint_id' => -7, 'duration' => 10.0, 'startTime' => 0, 'endTime' => 10.0],
            ['checkpoint_id' => 40, 'duration' => 10.0, 'startTime' => 10.0, 'endTime' => 20.0],
            ['checkpoint_id' => 999, 'duration' => 10.0, 'startTime' => 20.0, 'endTime' => 30.0],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['splits']);

    expect(LapTime::count())->toBe(0);
});

it('accepts checkpoint IDs submitted out of order as long as they form a contiguous 1..N set', function () {
    config(['webhook.hrl_query.enforce' => false]);
    fakeGameServerQuery();

    submitLap([
        'splits' => [
            ['checkpoint_id' => 3, 'duration' => 10.0, 'startTime' => 20.0, 'endTime' => 30.0],
            ['checkpoint_id' => 1, 'duration' => 10.0, 'startTime' => 0, 'endTime' => 10.0],
            ['checkpoint_id' => 2, 'duration' => 10.0, 'startTime' => 10.0, 'endTime' => 20.0],
        ],
    ])->assertOk();

    expect(Map::sole()->checkpoint_count)->toBe(3);
});

// TEST-01 audit follow-up (2026-07-09) — a real Any Order submission from a live game server was
// rejected by the contiguous-1..N check above: checkpoint IDs [1, 4, 5], while bloodgulch's real
// established checkpoint baseline is 5 (anonymised from the real payload; only
// player_hash/player_name/hrl_token/submission_id/port were changed). Investigated before
// "fixing" anything: Any Order means the *player* can complete checkpoints out of course
// sequence, not that the map's physical checkpoint set changes — a genuine Any Order lap on
// bloodgulch should still report all 5 checkpoints, just potentially unordered. The missing 2/3
// point to the game server's Lua script under-reporting splits in Any Order mode specifically —
// a producer-side bug, not a backend validation gap (this check doesn't even consult a map's
// learned baseline — it's a self-contained "is this a real 1..N set" check, independent of any
// particular map). The check is correctly race-type-agnostic; this test documents that and guards
// against a future "fix" re-introducing the wrong loosening.
it('rejects a real anonymised Any Order submission with an incomplete checkpoint set (Lua under-reporting)', function () {
    config(['webhook.hrl_query.enforce' => false]);
    fakeGameServerQuery();

    submitLap([
        'map_name' => 'bloodgulch',
        'player_hash' => 'feb7758227bdc1ef12e7c6bbb1567693',
        'player_name' => 'Effakt',
        'player_time' => 68.066666666667,
        'port' => 2304,
        'race_type' => 1,
        'submission_id' => 'golden-anyorder-0001',
        'splits' => [
            ['checkpoint_id' => 1, 'duration' => 9.3, 'startTime' => 11.5, 'endTime' => 20.8],
            ['checkpoint_id' => 4, 'duration' => 39.533333333333, 'startTime' => 20.8, 'endTime' => 60.333333333333],
            ['checkpoint_id' => 5, 'duration' => 19.233333333333, 'startTime' => 60.333333333333, 'endTime' => 79.566666666667],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['splits']);

    expect(LapTime::count())->toBe(0);
});

it('accepts an Any Order submission that reports every checkpoint, just out of course sequence', function () {
    config(['webhook.hrl_query.enforce' => false]);
    fakeGameServerQuery();

    submitLap([
        'race_type' => 1,
        'splits' => [
            ['checkpoint_id' => 4, 'duration' => 10.0, 'startTime' => 0, 'endTime' => 10.0],
            ['checkpoint_id' => 1, 'duration' => 10.0, 'startTime' => 10.0, 'endTime' => 20.0],
            ['checkpoint_id' => 5, 'duration' => 10.0, 'startTime' => 20.0, 'endTime' => 30.0],
            ['checkpoint_id' => 2, 'duration' => 10.0, 'startTime' => 30.0, 'endTime' => 40.0],
            ['checkpoint_id' => 3, 'duration' => 10.0, 'startTime' => 40.0, 'endTime' => 50.0],
        ],
    ])->assertOk();

    expect(Map::where('name', 'bloodgulch-anyorder')->sole())->checkpoint_count->toBe(5);
});

it('rejects a duplicate map name at the database level', function () {
    Map::factory()->create(['name' => 'some-map']);

    expect(fn () => Map::factory()->create(['name' => 'some-map']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('classifies a maps.name unique-constraint violation as such, not by elimination', function () {
    $mapNameViolation = new UniqueConstraintViolationException(
        'mysql', 'insert into `maps` ...', [],
        new PDOException("SQLSTATE[23000]: ... 'maps_name_unique'"),
    );
    $mapNameViolation->columns = ['name'];
    $mapNameViolation->index = 'maps_name_unique';

    $job = new ProcessNewLap(ip: '127.0.0.1', port: 2302, data: []);
    $violatesMapName = (new ReflectionMethod($job, 'violatesMapNameUniqueness'))->getClosure($job);
    $violatesServerIdentity = (new ReflectionMethod($job, 'violatesServerIdentityUniqueness'))->getClosure($job);

    expect($violatesMapName($mapNameViolation))->toBeTrue();
    expect($violatesServerIdentity($mapNameViolation))->toBeFalse();
});

it('rejects a duplicate (hash, name) player pair at the database level, but allows either alone to repeat', function () {
    Player::factory()->create(['hash' => 'shared-hash', 'name' => 'Alice']);

    // The real identity key is (hash, name) together — the game client no longer manufactures
    // one distinct hash per player, so unrelated players legitimately share a hash (confirmed
    // with the user, see docs/security.md). Same hash, different name: allowed.
    Player::factory()->create(['hash' => 'shared-hash', 'name' => 'Bob']);
    // Same name, different hash (the pre-existing "TAIIDOSH" scenario, docs/database.md): allowed.
    Player::factory()->create(['hash' => 'other-hash', 'name' => 'Alice']);

    expect(fn () => Player::factory()->create(['hash' => 'shared-hash', 'name' => 'Alice']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('classifies a players (hash, name) unique-constraint violation as such, not by elimination', function () {
    $playerIdentityViolation = new UniqueConstraintViolationException(
        'mysql', 'insert into `players` ...', [],
        new PDOException("SQLSTATE[23000]: ... 'players_hash_name_unique'"),
    );
    $playerIdentityViolation->columns = ['hash', 'name'];
    $playerIdentityViolation->index = 'players_hash_name_unique';

    $job = new ProcessNewLap(ip: '127.0.0.1', port: 2302, data: []);
    $violatesPlayerIdentity = (new ReflectionMethod($job, 'violatesPlayerIdentityUniqueness'))->getClosure($job);
    $violatesServerIdentity = (new ReflectionMethod($job, 'violatesServerIdentityUniqueness'))->getClosure($job);
    $violatesMapName = (new ReflectionMethod($job, 'violatesMapNameUniqueness'))->getClosure($job);

    expect($violatesPlayerIdentity($playerIdentityViolation))->toBeTrue();
    expect($violatesServerIdentity($playerIdentityViolation))->toBeFalse();
    expect($violatesMapName($playerIdentityViolation))->toBeFalse();

    // A partial/unrelated column set that merely contains 'hash' must not false-match — exact
    // column-set equality only, same precision fix as violatesMapNameUniqueness()'s 'name' check.
    $unrelatedHashViolation = new UniqueConstraintViolationException(
        'mysql', 'insert into `something_else` ...', [],
        new PDOException("SQLSTATE[23000]: ... 'something_else_unique'"),
    );
    $unrelatedHashViolation->columns = ['hash'];
    $unrelatedHashViolation->index = 'something_else_unique';

    expect($violatesPlayerIdentity($unrelatedHashViolation))->toBeFalse();
});

it('only lets one request establish a map\'s checkpoint-count baseline (CAS proof)', function () {
    // The real concurrent-request race (two simultaneous first-split submissions for the same
    // brand-new map both reading checkpoint_count as null before either writes) isn't
    // reproducible in a single-threaded test — but the conditional UPDATE ProcessNewLap::
    // resolveMap() relies on to prevent it is: only the FIRST such UPDATE against a still-null
    // row can ever succeed, so a "losing" request always observes the winner's value instead of
    // silently overwriting it.
    $map = Map::factory()->create(['checkpoint_count' => null]);

    $firstWon = Map::where('id', $map->id)->whereNull('checkpoint_count')->update(['checkpoint_count' => 5]);
    $secondWon = Map::where('id', $map->id)->whereNull('checkpoint_count')->update(['checkpoint_count' => 6]);

    expect($firstWon)->toBe(1)
        ->and($secondWon)->toBe(0)
        ->and($map->fresh()->checkpoint_count)->toBe(5);
});

it('rejects a further checkpoint-count fork once a map has hit its variant cap', function () {
    config(['webhook.hrl_query.enforce' => false, 'webhook.max_map_variants_per_name' => 1]);
    fakeGameServerQuery();

    submitLap(['splits' => splitsWithCheckpoints(5)])->assertOk();
    submitLap(['splits' => splitsWithCheckpoints(6), 'player_time' => 40])->assertOk();

    submitLap(['splits' => splitsWithCheckpoints(7), 'player_time' => 40])
        ->assertStatus(422)
        ->assertJson(['success' => false, 'reason' => 'checkpoint_layout_mismatch']);

    expect(Map::count())->toBe(2);
    expect(LapTime::count())->toBe(2);
});

it('reuses an already-forked variant without counting it against the per-map cap again', function () {
    config(['webhook.hrl_query.enforce' => false, 'webhook.max_map_variants_per_name' => 1]);
    fakeGameServerQuery();

    submitLap(['splits' => splitsWithCheckpoints(5)])->assertOk();
    submitLap(['splits' => splitsWithCheckpoints(6), 'player_time' => 40])->assertOk();
    submitLap(['splits' => splitsWithCheckpoints(6), 'player_time' => 41])->assertOk();

    expect(Map::count())->toBe(2);
    expect(LapTime::count())->toBe(3);
});

// race_type identity (2026-07-08 follow-up, docs/decisions.md) — a checkpoint-count mismatch
// forks into its own Map row; race_type now does too, instead of only changing the display
// label on one shared row. Real historical laps can never be retroactively attributed to a
// race_type (never persisted per-lap, see docs/roadmap.md), so index 0 (normal races) keeps the
// exact same `name` real historical data already has — only race_type 1/2 create a new row.
it('forks a distinct map identity for an Any Order submission, leaving the normal-race map untouched', function () {
    config(['webhook.hrl_query.enforce' => false]);
    fakeGameServerQuery();

    submitLap(['race_type' => 0])->assertOk();
    submitLap(['race_type' => 1, 'player_time' => 40])->assertOk();

    expect(Map::count())->toBe(2);
    $normal = Map::where('name', 'bloodgulch')->sole();
    $anyOrder = Map::where('name', 'bloodgulch-anyorder')->sole();

    expect($normal->label)->toBe('Bloodgulch')
        ->and($anyOrder->label)->toBe('Bloodgulch - Any Order');

    expect(LapTime::where('map_id', $normal->id)->count())->toBe(1);
    expect(LapTime::where('map_id', $anyOrder->id)->count())->toBe(1);
});

it('forks a distinct map identity for a Rally submission', function () {
    config(['webhook.hrl_query.enforce' => false]);
    fakeGameServerQuery();

    submitLap(['race_type' => 2])->assertOk();

    $map = Map::sole();
    expect($map->name)->toBe('bloodgulch-rally');
    expect($map->label)->toBe('Bloodgulch - Rally');
});

it('reuses the same race-type map identity across repeated submissions of that race_type', function () {
    config(['webhook.hrl_query.enforce' => false]);
    fakeGameServerQuery();

    submitLap(['race_type' => 1])->assertOk();
    submitLap(['race_type' => 1, 'player_time' => 40])->assertOk();

    expect(Map::count())->toBe(1);
    expect(LapTime::count())->toBe(2);
});

it('learns a separate checkpoint-count baseline per race-type map identity', function () {
    config(['webhook.hrl_query.enforce' => false]);
    fakeGameServerQuery();

    submitLap(['race_type' => 0, 'splits' => splitsWithCheckpoints(5)])->assertOk();
    submitLap(['race_type' => 1, 'splits' => splitsWithCheckpoints(6), 'player_time' => 40])->assertOk();

    expect(Map::count())->toBe(2);
    expect(Map::where('name', 'bloodgulch')->sole()->checkpoint_count)->toBe(5);
    expect(Map::where('name', 'bloodgulch-anyorder')->sole()->checkpoint_count)->toBe(6);
});

it('composes a checkpoint-count fork on top of a race-type identity without colliding names', function () {
    config(['webhook.hrl_query.enforce' => false]);
    fakeGameServerQuery();

    submitLap(['race_type' => 1, 'splits' => splitsWithCheckpoints(5)])->assertOk();
    submitLap(['race_type' => 1, 'splits' => splitsWithCheckpoints(6), 'player_time' => 40])->assertOk();

    expect(Map::count())->toBe(2);
    $baseline = Map::where('name', 'bloodgulch-anyorder')->sole();
    $variant = Map::where('name', 'bloodgulch-anyorder-splits-6')->sole();

    expect($baseline->checkpoint_count)->toBe(5)
        ->and($variant->checkpoint_count)->toBe(6)
        ->and($variant->label)->toBe('Bloodgulch - Any Order (6 CP)');
});
