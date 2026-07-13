<?php

namespace App\Jobs;

use App\Events\LapSubmitted;
use App\Events\LeaderboardUpdated;
use App\Exceptions\LapSubmissionConflictException;
use App\Exceptions\TooManyMapVariantsException;
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
    private const array MAP_ALIASES = [
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

    private const array RACE_TYPE_SUFFIXES = ['', 'Any Order', 'Rally'];

    /**
     * `race_type`'s own `Map`-identity suffix (2026-07-08 follow-up to the SEC-04 review — see
     * docs/decisions.md) — deliberately a SEPARATE array from RACE_TYPE_SUFFIXES above, not
     * derived from it: that one produces a human-readable *label* fragment ("Any Order"), this
     * one produces a machine-safe `Map.name` fragment (`-anyorder`) that can never collide with
     * resolveMapVariant()'s own `{name}-splits-{count}` suffix. Index 0 (normal races) is
     * deliberately empty — the far more common case (see docs/database.md) keeps using the
     * exact same `name` real historical data already has, so no data migration was needed to
     * ship this: only race_type 1/2 submissions create a NEW row going forward. Historical laps
     * recorded before this change can't be retroactively attributed to a race_type at all —
     * `race_type` was never persisted per-lap, only folded into a label string and a one-way
     * hash (see docs/roadmap.md's now-resolved open question) — so they stay under the plain
     * (race_type-0) row regardless of which race_type they actually were.
     */
    private const array RACE_TYPE_NAME_SUFFIXES = ['', '-anyorder', '-rally'];

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
     * @return array{success: bool, isNewRecord: bool, lapTime: float, bestTime: float, leaderboardPosition: array{position: int, total: int, top_time?: float, difference?: float}, globalLeaderboardPosition: array{position: int, total: int, top_time?: float, difference?: float}, personalBest: array{time: float, previousTime: ?float, isNewRecord: bool, improvement: ?float}}
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

            // Only `servers`' (ip, port, active_since) constraint, `maps`' (name) constraint
            // (SEC-04 review follow-up — see the add_unique_index_to_maps_name migration), or
            // `players`' (hash, name) constraint (PERF-02/security follow-up — see the
            // add_unique_index_to_players_name_hash migration) is expected to land here
            // otherwise — checked explicitly rather than assumed, so a future unrelated unique
            // constraint can't be silently mishandled as this race: a concurrent first-ever
            // submission for this exact ip:port (or the same map name/variant, or the same new
            // player) already created the row between this request's read and write. That row
            // exists now, so simply retrying the whole transaction once succeeds via
            // firstOrCreate()'s SELECT finding it.
            throw_if(! $this->violatesServerIdentityUniqueness($e)
                && ! $this->violatesMapNameUniqueness($e)
                && ! $this->violatesPlayerIdentityUniqueness($e), $e);

            $result = DB::transaction(fn (): array => $this->recordLap($hostname, $mapLabel, $submissionId, $contentHash));
        }

        $leaderboardPosition = $this->leaderboardPosition(
            $result['server']->id,
            $result['map']->id,
            $result['bestTime'],
            $result['newTime'],
        );

        // Same calculation, unscoped from this one server — the map's global (all-servers)
        // leaderboard position, matching the "nested vs. global leaderboard" split this app
        // already exposes elsewhere (ServerMapLeaderboard/MapLeaderboard, docs/architecture.md).
        $globalLeaderboardPosition = $this->leaderboardPosition(
            null,
            $result['map']->id,
            $result['bestTime'],
            $result['newTime'],
        );

        // Every submission broadcasts site-wide (Servers List header stats/"MOST ACTIVE" card,
        // Home's highlights — anything that changes on any attempt, not just an improvement).
        event(new LapSubmitted($result['server']->id, $result['map']->id));

        // This one, scoped and fired only on a genuine improvement, is what the two leaderboard
        // pages (ServerMapLeaderboard/MapLeaderboard) listen for — see docs/database.md's "Live
        // leaderboard updates" section.
        if ($result['isNewRecord']) {
            event(new LeaderboardUpdated($result['server']->id, $result['map']->id, $result['player']->id, $result['player']->name, $result['newTime'], $leaderboardPosition['position']));
        }

        return [
            'success' => true,
            'isNewRecord' => $result['isNewRecord'],
            'lapTime' => round($result['newTime'], 2),
            'bestTime' => round($result['bestTime'], 2),
            'leaderboardPosition' => $leaderboardPosition,
            'globalLeaderboardPosition' => $globalLeaderboardPosition,
            'personalBest' => $this->personalBestPayload($result['bestTime'], $result['previousBest'], $result['isNewRecord'], $result['newTime']),
        ];
    }

    /**
     * The actual server/player/map/lap-creation work, extracted so `handle()` can run it inside
     * a fresh `DB::transaction()` a second time (SEC-01 audit follow-up) if the first attempt's
     * `Server::firstOrCreate()` loses a race with a concurrent first-ever submission for the
     * same ip:port — see `handle()`'s catch block.
     *
     * @return array{server: Server, map: Map, player: Player, isNewRecord: bool, newTime: float, bestTime: float, previousBest: ?float}
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

        // Matched on (hash, name) together, not hash alone — the game client no longer
        // manufactures one distinct hash per player, so unrelated players can share a hash;
        // (hash, name) is the real identity key (confirmed against real data: zero existing
        // rows share both). See docs/security.md's "players.hash race condition" note.
        $player = Player::firstOrCreate(
            ['hash' => hash('sha256', $this->data['player_hash']), 'name' => $this->data['player_name']]
        );

        $map = $this->resolveMap($mapLabel);

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
            // The player's PB as it stood BEFORE this submission — null on a player's first ever
            // lap for this server+map. Kept separate from 'bestTime' above (which becomes the new
            // time itself once isNewRecord is true) so a "beat your PB by X seconds" comparison
            // stays possible.
            'previousBest' => $bestTime,
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
     * @return array{success: bool, isNewRecord: bool, lapTime: float, bestTime: float, leaderboardPosition: array{position: int, total: int, top_time?: float, difference?: float}, globalLeaderboardPosition: array{position: int, total: int, top_time?: float, difference?: float}, personalBest: array{time: float, previousTime: ?float, isNewRecord: bool, improvement: ?float}}
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
                'globalLeaderboardPosition' => ['position' => 0, 'total' => 0],
                'personalBest' => ['time' => 0.0, 'previousTime' => null, 'isNewRecord' => false, 'improvement' => null],
            ];
        }

        // `submission_hash` is null for laps recorded before this column existed — nothing to
        // compare against, so fall through to the ordinary replay rather than reject.
        throw_if($lapTime->submission_hash !== null && $lapTime->submission_hash !== $contentHash, LapSubmissionConflictException::class);

        $bestTime = (float) LapTime::where([
            'server_id' => $lapTime->server_id,
            'map_id' => $lapTime->map_id,
            'player_id' => $lapTime->player_id,
        ])->min('time');

        // The PB as it stood before THIS lap was ever recorded — excludes the replayed row itself
        // so a replay reports the same "previous best" a fresh submission of it would have.
        $previousBestRaw = LapTime::where([
            'server_id' => $lapTime->server_id,
            'map_id' => $lapTime->map_id,
            'player_id' => $lapTime->player_id,
        ])->where('id', '!=', $lapTime->id)->min('time');
        $previousBest = $previousBestRaw !== null ? (float) $previousBestRaw : null;

        $isNewRecord = (float) $lapTime->time === $bestTime;

        return [
            'success' => true,
            'isNewRecord' => $isNewRecord,
            'lapTime' => round((float) $lapTime->time, 2),
            'bestTime' => round($bestTime, 2),
            'leaderboardPosition' => $this->leaderboardPosition($lapTime->server_id, $lapTime->map_id, $bestTime, (float) $lapTime->time),
            'globalLeaderboardPosition' => $this->leaderboardPosition(null, $lapTime->map_id, $bestTime, (float) $lapTime->time),
            'personalBest' => $this->personalBestPayload($bestTime, $previousBest, $isNewRecord, (float) $lapTime->time),
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
     * Confirms a unique-constraint violation is specifically `maps`' `name` unique index (SEC-04
     * review follow-up) — a concurrent submission for the same brand-new map name, or two
     * concurrent mismatched submissions racing to create the same `{map_name}-splits-{count}`
     * variant, can both pass `firstOrCreate()`'s SELECT before either INSERT commits. SQLite
     * populates `$columns` (no index name); MySQL/Postgres populate `$index` instead. Checked as
     * an exact column-set match (`=== ['name']`), not `in_array` — `players`' `(hash, name)`
     * index (PERF-02/security follow-up) also has a `name` column, and SQLite never populates
     * `$index`, so a looser check would misclassify a players-identity race as a map-name race.
     */
    private function violatesMapNameUniqueness(UniqueConstraintViolationException $e): bool
    {
        return $e->columns === ['name']
            || str_contains($e->index ?? '', 'maps_name_unique');
    }

    /**
     * Confirms a unique-constraint violation is specifically `players`' `(hash, name)` identity
     * index (PERF-02/security follow-up) — a concurrent first-ever submission for the same new
     * player (same hash, same name) can both pass `firstOrCreate()`'s SELECT before either
     * INSERT commits. SQLite populates `$columns` (no index name); MySQL/Postgres populate
     * `$index` instead. Checked as an exact column-set match, not `in_array('hash', ...)` — the
     * same precision fix as `violatesMapNameUniqueness()`, so a future unrelated constraint that
     * happens to include a `hash` column can't be silently misclassified as this race.
     */
    private function violatesPlayerIdentityUniqueness(UniqueConstraintViolationException $e): bool
    {
        $columns = $e->columns;
        sort($columns);

        return $columns === ['hash', 'name']
            || str_contains($e->index ?? '', 'players_hash_name_unique');
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
        $label = self::MAP_ALIASES[$mapName] ?? $this->formatUnaliasedMapLabel($mapName);
        $suffix = self::RACE_TYPE_SUFFIXES[$raceType] ?? '';

        return $suffix !== '' ? "{$label} - {$suffix}" : $label;
    }

    /**
     * Fallback for any raw map name not in MAP_ALIASES above, so an unlisted map (e.g. a custom
     * or newly-added one) still gets a readable label instead of its literal machine name.
     * Splits on any run of `_`/`-` (single or double), title-cases each word, and lowercases a
     * bare version token (`V2`, `v3`, ...) so it reads as a suffix rather than a title word —
     * e.g. `atephobia__V2` -> `Atephobia v2`, `New_Mombasa_Race_v2` -> `New Mombasa Race v2`,
     * `Camtrack-Arena-Race` -> `Camtrack Arena Race`.
     */
    private function formatUnaliasedMapLabel(string $mapName): string
    {
        $words = preg_split('/[_-]+/', $mapName, -1, PREG_SPLIT_NO_EMPTY);

        $words = array_map(
            fn (string $word): string => preg_match('/^v\d+$/i', $word) === 1
                ? strtolower($word)
                : ucfirst(strtolower($word)),
            $words,
        );

        return implode(' ', $words);
    }

    /**
     * `race_type`'s own `Map`-identity name (2026-07-08 follow-up, docs/decisions.md) — see
     * RACE_TYPE_NAME_SUFFIXES' own docblock for why this is a separate machine-safe suffix from
     * `mapLabel()`'s human-readable one, and why index 0 is deliberately a no-op.
     */
    private function raceTypeMapName(string $mapName, int $raceType): string
    {
        return $mapName.(self::RACE_TYPE_NAME_SUFFIXES[$raceType] ?? '');
    }

    /**
     * SEC-04 audit follow-up (docs/security.md) — a map's physical checkpoint layout is fixed
     * (confirmed against real data: every map's recorded checkpoint IDs are a stable, contiguous
     * `1..N` set across every lap ever submitted for it), so it's learned once from the first
     * split-bearing submission and enforced after that, rather than left unbounded.
     *
     * A submission with no splits (most of them — see docs/database.md's sparse-splits note)
     * always uses the plain map and never establishes or checks a baseline; `checkpoint_count`
     * stays null until a split-bearing submission actually arrives. `StoreLapTimeRequest`
     * already caps the raw split count at `config('webhook.max_checkpoints')` before this ever
     * runs, so `$submittedCheckpointCount` here is always sane in absolute terms — this method
     * only decides whether it matches *this specific map's* established count.
     *
     * A mismatch doesn't reject the lap or overwrite the original map's baseline: maps are only
     * ever added, never redesigned in place (confirmed with the user), so a different checkpoint
     * count for the same underlying map file means a genuinely different course sharing that
     * file, not corruption of the original leaderboard or a hostile payload. It's forked into
     * its own `{map_name}-splits-{count}` map identity instead, with its own baseline and its
     * own leaderboard. `config('webhook.max_map_variants_per_name')` caps how many such forks one
     * base map identity can accumulate (SEC-04 review follow-up) — beyond that, a further
     * mismatch is rejected outright (`TooManyMapVariantsException`) instead of forking
     * indefinitely, since an unbounded number of "distinct courses" sharing one file looks like
     * abuse rather than real level design.
     *
     * `race_type` gets its own `Map` identity too (2026-07-08 follow-up, docs/decisions.md) —
     * `raceTypeMapName()` folds it into the base name BEFORE any of the above runs, so a
     * checkpoint-count fork always forks the correct race_type-specific map, never the plain one.
     * Index 0 (normal races) keeps the exact same name real historical data already has, so
     * existing rows and every already-existing test/behavior for the common case are unaffected.
     *
     * @throws TooManyMapVariantsException
     */
    private function resolveMap(string $mapLabel): Map
    {
        $mapName = $this->raceTypeMapName($this->data['map_name'], (int) $this->data['race_type']);
        $map = Map::firstOrCreate(['name' => $mapName], ['label' => $mapLabel]);

        $splits = $this->data['splits'] ?? [];

        if ($splits === []) {
            return $map;
        }

        $submittedCheckpointCount = count(array_unique(array_column($splits, 'checkpoint_id')));

        if ($map->checkpoint_count === null) {
            // Concurrency-safe baseline establishment (SEC-04 review follow-up) — a plain
            // read-then-write here let two concurrent first-split submissions for the same map
            // both "win" with different counts. This conditional UPDATE only succeeds for
            // whichever request's write actually lands first; a request that loses the race
            // re-reads whatever count won and falls through to the same mismatch handling any
            // later submission gets.
            $wonRace = Map::where('id', $map->id)->whereNull('checkpoint_count')
                ->update(['checkpoint_count' => $submittedCheckpointCount]);

            if ($wonRace === 1) {
                $map->checkpoint_count = $submittedCheckpointCount;

                return $map;
            }

            $map->refresh();
        }

        if ($map->checkpoint_count === $submittedCheckpointCount) {
            return $map;
        }

        return $this->resolveMapVariant($mapName, $mapLabel, $submittedCheckpointCount);
    }

    /**
     * TEST-01 audit follow-up (2026-07-09) — a real two-process MySQL concurrency test
     * (`tests/Feature/MapVariantCapConcurrencyTest.php`, SQLite can't provide meaningful
     * evidence for this) caught a genuine race here: the original plain
     * count-then-`firstOrCreate()` let two concurrent requests proposing two *different* new
     * checkpoint counts both read the same under-cap count, both pass the check, and both
     * insert — pushing the real variant count one past the configured cap. Fixed by locking the
     * *base* map row (`lockForUpdate()` inside a transaction) before counting: a concurrent
     * request for the same base map name blocks until the first transaction commits or rolls
     * back, so the count-then-insert is effectively serialized per base map name — the same
     * "lock the thing two concurrent writers actually contend on" idea as the checkpoint-count
     * baseline's conditional `UPDATE` above, just via pessimistic locking instead of a CAS,
     * since a cap check has to look at other rows' existence, not just one row's own column.
     *
     * @throws TooManyMapVariantsException
     */
    private function resolveMapVariant(string $mapName, string $mapLabel, int $checkpointCount): Map
    {
        return DB::transaction(function () use ($mapName, $mapLabel, $checkpointCount): Map {
            $this->acquireMapVariantLock($mapName);

            $variantName = "{$mapName}-splits-{$checkpointCount}";
            $existingVariant = Map::where('name', $variantName)->first();

            if ($existingVariant !== null) {
                return $existingVariant;
            }

            $existingVariantCount = $this->countExistingMapVariants("{$mapName}-splits-%");

            throw_if($existingVariantCount >= config('webhook.max_map_variants_per_name'), TooManyMapVariantsException::class);

            return Map::firstOrCreate(
                ['name' => $variantName],
                ['label' => "{$mapLabel} ({$checkpointCount} CP)", 'checkpoint_count' => $checkpointCount],
            );
        });
    }

    /**
     * Extracted as its own overridable step (code review follow-up, 2026-07-09) purely so
     * `MapVariantCapConcurrencyTest.php` can inject a real delay here via a test-only subclass —
     * without a genuine pause between "count read" and "insert," a lucky scheduler could let one
     * worker's entire count-then-insert finish before the other even starts counting, which
     * would produce the same passing assertions (one OK, one REJECTED, count === cap) whether or
     * not `lockForUpdate()` above is actually doing anything. See the worker script's docblock.
     */
    protected function countExistingMapVariants(string $namePattern): int
    {
        return Map::where('name', 'like', $namePattern)->count();
    }

    /**
     * Extracted as its own overridable step (code review follow-up, 2026-07-09), same reason as
     * `countExistingMapVariants()` above — lets `MapVariantCapConcurrencyTest.php`'s negative
     * control substitute a deliberately no-op lock via a test-only subclass, to prove (in CI,
     * permanently, not just via a one-off manual edit) that this test harness genuinely detects
     * a missing lock rather than passing by scheduling luck.
     */
    protected function acquireMapVariantLock(string $mapName): void
    {
        Map::where('name', $mapName)->lockForUpdate()->first();
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
     * `$serverId` null means the map's GLOBAL position — across every active server, not just
     * one — matching the "nested vs. global leaderboard" split this app already exposes
     * elsewhere (ServerMapLeaderboard/MapLeaderboard). A soft-deleted server's laps are excluded
     * from that global scope (`whereHas('server')`, same convention as `GlobalRanking`); a
     * specific `$serverId` is always one already-resolved, non-deleted server, so that filter is
     * skipped there to keep the existing server-scoped behavior unchanged.
     *
     * @return array{position: int, total: int, top_time?: float, difference?: float}
     */
    private function leaderboardPosition(?int $serverId, int $mapId, float $timeForPosition, float $timeForDifference): array
    {
        $bestTimes = LapTime::where('map_id', $mapId)
            ->when($serverId !== null, fn ($query) => $query->where('server_id', $serverId))
            ->when($serverId === null, fn ($query) => $query->whereHas('server'))
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
            // snake_case (not topTime) — the real Lua/SAPP client (hrl.lua) reads `lb.top_time`
            // directly; confirmed 2026-07-07 by reading the actual deployed script, not guessed.
            $position['top_time'] = $topTime;
            $position['difference'] = round($timeForDifference - $topTime, 2);
        }

        return $position;
    }

    /**
     * Groups the PB-comparison fields already derived by `recordLap()`/`replayDuplicateSubmission()`
     * into one payload, so `handle()` and the duplicate-replay path can't drift out of sync on
     * what "PB" means in the response. `previousTime`/`improvement` are null when there's no
     * earlier lap to compare against (a player's first-ever lap for this server+map).
     *
     * @return array{time: float, previousTime: ?float, isNewRecord: bool, improvement: ?float}
     */
    private function personalBestPayload(float $bestTime, ?float $previousBest, bool $isNewRecord, float $newTime): array
    {
        return [
            'time' => round($bestTime, 2),
            'previousTime' => $previousBest !== null ? round($previousBest, 2) : null,
            'isNewRecord' => $isNewRecord,
            'improvement' => ($isNewRecord && $previousBest !== null) ? round($previousBest - $newTime, 2) : null,
        ];
    }
}
