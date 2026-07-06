<?php

namespace App\Jobs;

use App\Events\LapSubmitted;
use App\Events\LeaderboardUpdated;
use App\Exceptions\LapSubmissionConflictException;
use App\Helpers\GameServerQuery;
use App\Helpers\LapSubmissionHash;
use App\Models\LapTime;
use App\Models\LapTimeSplit;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Rebuilt from `ProcessNewLap.php-legacy`. Invoked directly (`app(ProcessNewLap::class)->handle(...)`)
 * from the webhook controller rather than queued — matches the old app's actual runtime
 * behavior (it declared `ShouldQueue` but always called `->handle()` synchronously, never
 * `::dispatch()`), because the game server needs its leaderboard position back in the same
 * HTTP response. See docs/database.md's "Webhook → job flow" section.
 *
 * Behavioral change from the old app (decided 2026-07-06): every submitted lap is now logged,
 * not only personal-best improvements — the old app silently discarded non-improving laps
 * after validating them.
 */
class ProcessNewLap
{
    /** Hardcoded machine-name → display-name aliases, ported verbatim from the legacy job. */
    private const MAP_ALIASES = [
        'beavercreek' => 'Battle Creek',
        'bloodgulch' => 'Bloodgulch',
        'boardingaction' => 'Boarding Action',
        'chillout' => 'Chillout',
        'putput' => 'Chiron TL-34',
        'damnation' => 'Damnation',
        'dangercanyon' => 'Danger Canyon',
        'deathisland' => 'Death Island',
        'carousel' => 'Derelict',
        'gephyrophobia' => 'Gephyrophobia',
        'hangemhigh' => "Hang 'Em High",
        'icefields' => 'Ice Fields',
        'infinity' => 'Infinity',
        'longest' => 'Longest',
        'prisoner' => 'Prisoner',
        'ratrace' => 'Rat Race',
        'sidewinder' => 'Sidewinder',
        'timberland' => 'Timberland',
        'wizard' => 'Wizard',
    ];

    private const RACE_TYPE_SUFFIXES = ['', 'Any Order', 'Rally'];

    /**
     * @param  array{map_name: string, player_hash: string, player_name: string, player_time: float, race_type: int, submission_id: string|null, splits: array<int, array{checkpoint_id: int, duration: float, startTime: float|null, endTime: float|null}>|null}  $data
     * @param  array<string, string>|false|null  $liveQueryResponse  Already-fetched UDP query response
     *                                                               (SEC-01's LapSubmissionVerifier queries the
     *                                                               same ip:port before this job runs) — reused
     *                                                               here instead of querying the server a second
     *                                                               time. `null` means verification was disabled/
     *                                                               didn't run — resolveHostname() falls back to
     *                                                               querying itself, same as before. `false` means
     *                                                               verification DID run and got no response at
     *                                                               all — resolveHostname() does NOT retry a third
     *                                                               time in that case (SEC-01 audit follow-up: the
     *                                                               verifier's own retry already failed twice
     *                                                               against the same ip:port).
     */
    public function __construct(
        private readonly string $ip,
        private readonly int $port,
        private readonly array $data,
        private readonly array|false|null $liveQueryResponse = null,
    ) {}

    /**
     * @return array{success: bool, isNewRecord: bool, lapTime: float, bestTime: float, leaderboardPosition: array{position: int, total: int, topTime?: float, difference?: float}}
     */
    public function handle(GameServerQuery $query): array
    {
        $hostname = $this->resolveHostname($query);
        $mapLabel = $this->mapLabel($this->data['map_name'], $this->data['race_type']);
        $submissionId = $this->data['submission_id'] ?? null;
        $contentHash = LapSubmissionHash::compute($this->data);

        try {
            $result = DB::transaction(fn (): array => $this->recordLap($hostname, $mapLabel, $submissionId, $contentHash));
        } catch (UniqueConstraintViolationException $e) {
            // Two DIFFERENT unique constraints can land here (SEC-01 audit follow-up) — they
            // need different responses, so check which one actually fired rather than assuming:
            if ($submissionId !== null && $this->violatesSubmissionIdUniqueness($e)) {
                // The DB-level source of truth for "was this exact submission already
                // recorded," independent of (and outlasting) the cache-based idempotency guard
                // in LapSubmissionController — see docs/security.md.
                return $this->replayDuplicateSubmission($submissionId, $contentHash);
            }

            // Only `servers`' (ip, port, active_since) constraint is expected to land here
            // otherwise (SEC-01 audit follow-up) — checked explicitly rather than assumed, so a
            // future unrelated unique constraint can't be silently mishandled as this race: a
            // concurrent first-ever submission for this exact ip:port already created the Server
            // row between this request's read and write. That row exists now, so simply
            // retrying the whole transaction once succeeds via firstOrCreate()'s SELECT finding
            // it.
            if (! $this->violatesServerIdentityUniqueness($e)) {
                throw $e;
            }

            $result = DB::transaction(fn (): array => $this->recordLap($hostname, $mapLabel, $submissionId, $contentHash));
        }

        $leaderboardPosition = $this->leaderboardPosition(
            $result['server']->id,
            $result['map']->id,
            $result['bestTime'],
            $result['newTime'],
        );

        // Every submission broadcasts site-wide (Servers List header stats/"MOST ACTIVE" card,
        // Home's highlights — anything that changes on any attempt, not just an improvement).
        LapSubmitted::dispatch($result['server']->id, $result['map']->id);

        // This one, scoped and fired only on a genuine improvement, is what the two leaderboard
        // pages (ServerMapLeaderboard/MapLeaderboard) listen for — see docs/database.md's "Live
        // leaderboard updates" section.
        if ($result['isNewRecord']) {
            LeaderboardUpdated::dispatch(
                $result['server']->id,
                $result['map']->id,
                $result['player']->id,
                $result['player']->name,
                $result['newTime'],
                $leaderboardPosition['position'],
            );
        }

        return [
            'success' => true,
            'isNewRecord' => $result['isNewRecord'],
            'lapTime' => round($result['newTime'], 2),
            'bestTime' => round($result['bestTime'], 2),
            'leaderboardPosition' => $leaderboardPosition,
        ];
    }

    /**
     * The actual server/player/map/lap-creation work, extracted so `handle()` can run it inside
     * a fresh `DB::transaction()` a second time (SEC-01 audit follow-up) if the first attempt's
     * `Server::firstOrCreate()` loses a race with a concurrent first-ever submission for the
     * same ip:port — see `handle()`'s catch block.
     *
     * @return array{server: Server, map: Map, player: Player, isNewRecord: bool, newTime: float, bestTime: float}
     */
    private function recordLap(?string $hostname, string $mapLabel, ?string $submissionId, string $contentHash): array
    {
        $server = Server::firstOrCreate(
            ['ip' => $this->ip, 'port' => (string) $this->port],
            ['name' => $hostname ?? "Unknown ({$this->ip}:{$this->port})"]
        );

        if ($hostname !== null && $server->name !== $hostname) {
            $server->update(['name' => $hostname]);
        }

        $player = Player::firstOrCreate(
            ['hash' => hash('sha256', $this->data['player_hash'])],
            ['name' => $this->data['player_name']]
        );

        $map = Map::firstOrCreate(
            ['name' => $this->data['map_name']],
            ['label' => $mapLabel]
        );

        // syncWithoutDetaching checks for an existing pivot row before inserting, unlike the
        // legacy insertOrIgnore-without-a-unique-constraint approach that silently inserted a
        // fresh duplicate on every submission — see docs/database.md's "Duplicate pivot rows".
        $server->players()->syncWithoutDetaching([$player->id]);
        $server->maps()->syncWithoutDetaching([$map->id]);

        $newTime = (float) $this->data['player_time'];

        $bestTimeRaw = LapTime::where([
            'server_id' => $server->id,
            'map_id' => $map->id,
            'player_id' => $player->id,
        ])->min('time');

        $bestTime = $bestTimeRaw !== null ? (float) $bestTimeRaw : null;
        $isNewRecord = $bestTime === null || $newTime < $bestTime;

        // Logged unconditionally (see class docblock) — not gated on beating the existing best.
        // `submission_id` is null for laps submitted without one (older Lua scripts) — the
        // (server_id, submission_id) unique index (SEC-01 audit follow-up, see the
        // add_submission_id_to_lap_times_table migration) treats multiple nulls as distinct, so
        // this never collides for them.
        $lapTime = LapTime::create([
            'server_id' => $server->id,
            'map_id' => $map->id,
            'player_id' => $player->id,
            'time' => $newTime,
            'submission_id' => $submissionId,
            'submission_hash' => $contentHash,
        ]);

        if (! empty($this->data['splits'])) {
            $now = now();

            LapTimeSplit::insert(array_map(fn (array $split): array => [
                'lap_time_id' => $lapTime->id,
                'checkpoint_id' => $split['checkpoint_id'],
                'duration' => $split['duration'],
                'start_time' => $split['startTime'] ?? null,
                'end_time' => $split['endTime'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $this->data['splits']));
        }

        return [
            'server' => $server,
            'map' => $map,
            'player' => $player,
            'isNewRecord' => $isNewRecord,
            'newTime' => $newTime,
            'bestTime' => $isNewRecord ? $newTime : $bestTime,
        ];
    }

    /**
     * A duplicate `submission_id` retry (see `handle()`'s catch block) never re-broadcasts and
     * never re-derives "was this a new record" from scratch — it reports the CURRENT truth about
     * the already-recorded lap (SEC-01 audit follow-up). This is a best-effort fallback for when
     * the cache-based idempotency guard's stored original response is gone (restart, eviction, a
     * very late retry) — the common case is still an exact cached-response replay, handled
     * entirely in `LapSubmissionController`, before this job ever runs a second time.
     *
     *
     * @return array{success: bool, isNewRecord: bool, lapTime: float, bestTime: float, leaderboardPosition: array{position: int, total: int, topTime?: float, difference?: float}}
     *
     * @throws LapSubmissionConflictException if the reused submission_id's stored content
     *                                        fingerprint no longer matches this request's
     *                                        (SEC-01 audit follow-up) — the cache-based guard
     *                                        already catches this while its own copy of the
     *                                        hash is still live; this is the durable fallback
     *                                        for once that has expired, been evicted, or the
     *                                        app has restarted.
     */
    private function replayDuplicateSubmission(string $submissionId, string $contentHash): array
    {
        $server = Server::where(['ip' => $this->ip, 'port' => (string) $this->port])->first();
        $lapTime = $server !== null
            ? LapTime::where('server_id', $server->id)->where('submission_id', $submissionId)->first()
            : null;

        // Should be unreachable — a unique-constraint violation on (server_id, submission_id)
        // implies a matching row exists — but fail safely rather than fatal if it somehow isn't.
        if ($lapTime === null) {
            return [
                'success' => false,
                'isNewRecord' => false,
                'lapTime' => 0.0,
                'bestTime' => 0.0,
                'leaderboardPosition' => ['position' => 0, 'total' => 0],
            ];
        }

        // `submission_hash` is null for laps recorded before this column existed — nothing to
        // compare against, so fall through to the ordinary replay rather than reject.
        if ($lapTime->submission_hash !== null && $lapTime->submission_hash !== $contentHash) {
            throw new LapSubmissionConflictException;
        }

        $bestTime = (float) LapTime::where([
            'server_id' => $lapTime->server_id,
            'map_id' => $lapTime->map_id,
            'player_id' => $lapTime->player_id,
        ])->min('time');

        return [
            'success' => true,
            'isNewRecord' => (float) $lapTime->time === $bestTime,
            'lapTime' => round((float) $lapTime->time, 2),
            'bestTime' => round($bestTime, 2),
            'leaderboardPosition' => $this->leaderboardPosition($lapTime->server_id, $lapTime->map_id, $bestTime, (float) $lapTime->time),
        ];
    }

    /**
     * Distinguishes which of `lap_times`' TWO unique constraints actually fired (SEC-01 audit
     * follow-up) — `UniqueConstraintViolationException::$columns`/`$index` tell us precisely,
     * rather than assuming from a generic SQLSTATE code the way an earlier version of this
     * method did (which couldn't have told a real submission_id collision apart from some other
     * integrity error, e.g. a foreign-key violation, misreporting either as a safe-to-replay
     * duplicate). SQLite populates `$columns` (no index name); MySQL/Postgres populate `$index`
     * (the constraint/key name) instead — checking both covers every driver this app runs on.
     */
    private function violatesSubmissionIdUniqueness(UniqueConstraintViolationException $e): bool
    {
        return in_array('submission_id', $e->columns, true)
            || str_contains($e->index ?? '', 'submission_id');
    }

    /**
     * Confirms a unique-constraint violation is specifically `servers`' `(ip, port,
     * active_since)` identity index (SEC-01 audit follow-up), rather than assuming it by
     * elimination — a future unrelated unique constraint on this table would otherwise be
     * silently mishandled as this race and retried instead of surfaced. SQLite populates
     * `$columns` (no index name); MySQL/Postgres populate `$index` instead.
     */
    private function violatesServerIdentityUniqueness(UniqueConstraintViolationException $e): bool
    {
        return in_array('active_since', $e->columns, true)
            || str_contains($e->index ?? '', 'servers_ip_port_active_since_unique');
    }

    /**
     * A failed live query no longer aborts the whole submission (the old app dropped the lap
     * entirely if the UDP query failed) — a temporary game-server query hiccup shouldn't
     * silently discard real lap data. A brand-new, never-before-seen server just gets a
     * placeholder name until a later successful query updates it.
     */
    private function resolveHostname(GameServerQuery $query): ?string
    {
        if ($this->liveQueryResponse === false) {
            // SEC-01 audit follow-up: LapSubmissionVerifier already tried this exact ip:port
            // (with its own retry) and got nothing back — a third attempt here is essentially
            // certain to fail too, and just burns another full timeout window. Treat as
            // unresolved directly rather than querying again.
            return null;
        }

        $response = $this->liveQueryResponse ?? $query->query($this->ip, $this->port);

        if ($response === false) {
            Log::warning('QueryServer failed, proceeding without a live hostname', [
                'ip' => $this->ip,
                'port' => $this->port,
                'error' => $query->getError(),
            ]);

            return null;
        }

        $hostname = trim($response['hostname'] ?? '');

        return $hostname !== '' ? $hostname : null;
    }

    private function mapLabel(string $mapName, int $raceType): string
    {
        $label = self::MAP_ALIASES[$mapName] ?? $mapName;
        $suffix = self::RACE_TYPE_SUFFIXES[$raceType] ?? '';

        return $suffix !== '' ? "{$label} - {$suffix}" : $label;
    }

    /**
     * Filters in PHP rather than SQL — see docs/decisions.md: a bound parameter compared
     * against an aggregate (`HAVING MIN(time) < ?` or an outer `WHERE` on a subquery's
     * aggregate alias) is silently ignored by this environment's bundled SQLite/PDO build
     * (the same comparison against a literal value works correctly). Small-scale, in-PHP
     * filtering sidesteps the bug entirely and matches this app's existing "derive fresh in
     * PHP" precedent (`GlobalRanking`, `MostActiveServer`) — real scale per map is at most a
     * few hundred players (see docs/database.md).
     *
     * @return array{position: int, total: int, topTime?: float, difference?: float}
     */
    private function leaderboardPosition(int $serverId, int $mapId, float $timeForPosition, float $timeForDifference): array
    {
        $bestTimes = LapTime::where('server_id', $serverId)
            ->where('map_id', $mapId)
            ->selectRaw('MIN(time) as best_time')
            ->groupBy('player_id')
            ->pluck('best_time')
            ->map(fn (string|float $time): float => round((float) $time, 2))
            ->sort()
            ->values();

        $rounded = round($timeForPosition, 2);
        $betterCount = $bestTimes->filter(fn (float $time): bool => $time < $rounded)->count();

        $position = ['position' => $betterCount + 1, 'total' => $bestTimes->count()];

        if ($bestTimes->isNotEmpty()) {
            $topTime = $bestTimes->first();
            $position['topTime'] = $topTime;
            $position['difference'] = round($timeForDifference - $topTime, 2);
        }

        return $position;
    }
}
