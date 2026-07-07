<?php

namespace App\Livewire;

use App\Models\GlobalRanking;
use App\Models\LapTime;
use App\Models\Map;
use App\Models\MostActiveServer;
use App\Models\Player;
use App\Models\RecordHistory;
use App\Models\Server;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Home', 'active' => 'home', 'description' => 'Halo Race Leaderboard is a fully public leaderboard that any Halo server can opt in to have track their times.'])]
class Home extends Component
{
    /** Recency window shared by every windowed highlight below — see docs/homepage.md's "needs confirming" note. */
    private const RECENCY_DAYS = 7;

    /**
     * PERF-01 follow-up (2026-07-08): even after the request-scoped dedup below, one full
     * computation still costs ~1.5s/94 queries at real scale (confirmed via profiling — DB time
     * is only ~170ms of that, the rest is PHP-side hydration/ranking) and every visitor loading
     * the homepage between two lap submissions was paying that cost independently, identically —
     * a textbook cache case: the result is the same for every anonymous visitor (this page has
     * no per-user data) and only changes when a new lap is submitted. Invalidated by
     * `App\Listeners\InvalidateHomeHighlightsCache` on `App\Events\LapSubmitted`; the TTL here is
     * only a safety net for the (rare, not currently triggered by anything) case of the
     * underlying data changing without a lap being submitted, e.g. a server being archived.
     *
     * This is a *prefix*, not the literal cache key — see `GENERATION_KEY` and
     * `rememberHighlights()` for why a plain `Cache::forget()` on one fixed key isn't safe here.
     * Public (not private) so `InvalidateHomeHighlightsCache` can reference the same values
     * rather than duplicating the string literals.
     */
    public const CACHE_KEY = 'home:highlights';

    /**
     * A monotonic counter, bumped (not the data itself deleted) on every invalidation — fixes a
     * real race a plain `Cache::forget(CACHE_KEY)` has (caught 2026-07-08, before it shipped to
     * production): if a new lap arrives *while* another request's cache-miss rebuild is still
     * running, `forget()` clears the key, but the in-flight rebuild — computed against the
     * *old* data — finishes afterward and writes its now-stale result right back under that same
     * key, undoing the invalidation. `App\Listeners\InvalidateHomeHighlightsCache` increments
     * this instead of forgetting `CACHE_KEY` directly; every read derives the actual data key as
     * `CACHE_KEY:{generation}`, so a stale in-flight rebuild started under the old generation
     * writes to an *abandoned* key nobody reads anymore (harmless, left to expire via its own
     * TTL) instead of overwriting the current one.
     */
    public const GENERATION_KEY = 'home:highlights:gen';

    private const CACHE_TTL_MINUTES = 10;

    /** How long a request will wait for another request's in-flight rebuild before giving up and computing its own — see `rememberHighlights()`. */
    private const LOCK_WAIT_SECONDS = 5;

    /** How long the rebuild lock itself is held before it's considered abandoned (e.g. the holder's process died mid-computation) and another request may acquire it. */
    private const LOCK_TIMEOUT_SECONDS = 10;

    public array $highlights = [];

    public array $quickStats = [];

    /**
     * Memoizes `GlobalRanking::forPlayer($playerId, excludeLapId: $lapId)` within one
     * `loadHighlights()` call (PERF-01 fix, 2026-07-08) — see `forPlayerBeforeLap()`.
     *
     * @var array<string, array<string, mixed>|null>
     */
    private array $forPlayerBeforeLapCache = [];

    public function mount(): void
    {
        $this->loadHighlights();
    }

    /**
     * Live update (roadmap item 16 follow-up) — every submitted lap can change these highlights
     * (Quick Stats, Live Stats Snapshot, Most Active Server, and any record/improvement/
     * achievement), not just a PB on one specific map, so this listens on the site-wide
     * `activity` channel rather than the map-scoped `LeaderboardUpdated` one.
     */
    #[On('echo:activity,.lap.submitted')]
    public function loadHighlights(): void
    {
        ['highlights' => $this->highlights, 'quickStats' => $this->quickStats] = $this->rememberHighlights();
    }

    /**
     * Cached as one blob, not per-piece — this whole computation is identical for every
     * anonymous visitor and only changes when a new lap is submitted (see `CACHE_KEY`'s
     * docblock), so a cache hit skips every query and every PHP-side ranking pass below
     * entirely, not just the "current ranking" portion PERF-01's first pass deduped.
     *
     * Two real problems a bare `Cache::remember()` has, both fixed here (caught 2026-07-08,
     * before shipping to production):
     *
     * 1. **Stampede**: on a cache miss, every concurrently-connected visitor would independently
     *    run the full ~1.5s/94-query rebuild at once — exactly the "every live lap triggers this
     *    for every visitor" problem this cache exists to solve, just moved to the moment right
     *    after invalidation instead of gone. A `Cache::lock()` means only one request actually
     *    rebuilds; the rest wait up to `LOCK_WAIT_SECONDS` for it to finish, then read its
     *    result — the `Cache::get($key)` immediately after acquiring the lock (not a blind
     *    rebuild) is the "recheck" half of double-checked locking, since whoever held the lock
     *    before us likely already populated this exact key while we were waiting.
     * 2. **Stale write after invalidation**: see `GENERATION_KEY`'s docblock — reading the
     *    generation *before* checking the cache, and deriving the data key from it, means a
     *    rebuild that started under an old generation can never clobber a newer one.
     *
     * @return array{highlights: list<array{type: string, data: array}>, quickStats: array{players: int, servers: int, laps: int}}
     */
    private function rememberHighlights(): array
    {
        $key = self::CACHE_KEY.':'.Cache::get(self::GENERATION_KEY, 0);

        if (($cached = Cache::get($key)) !== null) {
            return $cached;
        }

        try {
            return Cache::lock($key.':lock', self::LOCK_TIMEOUT_SECONDS)
                ->block(self::LOCK_WAIT_SECONDS, function () use ($key): array {
                    if (($cached = Cache::get($key)) !== null) {
                        return $cached;
                    }

                    return Cache::remember($key, now()->addMinutes(self::CACHE_TTL_MINUTES), fn (): array => $this->computeHighlights());
                });
        } catch (LockTimeoutException) {
            // Every waiting request gave up on the lock holder (still running, or its process
            // died mid-computation without releasing) — compute directly rather than fail the
            // request. Slower than a cache hit, but never worse than not having this cache at all.
            return $this->computeHighlights();
        }
    }

    /**
     * The actual per-request computation PERF-01 profiled at ~1.5s/94 queries — extracted out of
     * `loadHighlights()` so it can sit behind the cache above without changing its own logic.
     *
     * @return array{highlights: list<array{type: string, data: array}>, quickStats: array{players: int, servers: int, laps: int}}
     */
    private function computeHighlights(): array
    {
        // Shared once per request (PERF-01 fix, 2026-07-08) — `fastestImprovements()` and
        // `achievements()` each independently queried this exact same 7-day lap window, and each
        // called `GlobalRanking::forPlayer()`/`mapRank()` with no `excludeLapId` (i.e. "current"
        // rank) once per recent lap, every call re-running the full ranking computation over
        // *every* lap in the table from scratch. Measured before this fix: 132 queries / ~2.3s
        // for one `loadHighlights()` call at ~1,700 laps (see docs/performance.md). Computing the
        // "current" ranking snapshot once here and looking players up in it cuts out that
        // redundancy entirely; only the `excludeLapId`-varying ("what would it have looked like
        // without this one lap") calls still need their own per-lap computation, since those
        // genuinely differ per lap and can't be shared.
        $recentLaps = LapTime::whereHas('server')
            ->where('created_at', '>=', now()->subDays(self::RECENCY_DAYS))
            ->with(['player', 'map'])
            ->get();

        $currentScoresByPlayer = collect(GlobalRanking::scores())->keyBy('playerId')->all();

        // Six candidate highlight blocks, keyed by type — an empty array means "nothing to show
        // this round" (the fixed-priority selection below skips it), same contract as the mock
        // version.
        $candidates = [
            'records' => $this->latestRecords(),
            'most-active-server' => $this->mostActiveServers(),
            'fastest-improvements' => $this->fastestImprovements($recentLaps, $currentScoresByPlayer),
            'new-content' => $this->newContent(),
            'achievements' => $this->achievements($recentLaps, $currentScoresByPlayer),
            'live-stats' => $this->liveStats(),
        ];

        $priority = ['records', 'most-active-server', 'fastest-improvements', 'new-content', 'achievements', 'live-stats'];

        $highlights = collect($priority)
            ->map(fn (string $type): array => ['type' => $type, 'data' => $candidates[$type]])
            ->filter(fn (array $block): bool => ! empty($block['data']))
            ->take(3)
            ->values()
            ->all();

        $quickStats = [
            'players' => Player::count(),
            'servers' => Server::count(),
            'laps' => LapTime::count(),
        ];

        return ['highlights' => $highlights, 'quickStats' => $quickStats];
    }

    /**
     * Most recent real record-breaking events (App\Models\RecordHistory, roadmap item 13),
     * within the recency window. A genuine point-in-time derivation — this isn't "current course
     * records," it's "when did a lap actually become the new fastest time on its map," which is
     * why it needed its own calculator rather than reusing GlobalRanking's excludeLapId trick
     * (see docs/decisions.md).
     *
     * @return list<array{map: string, time: string, player: string, server: string, ago: string}>
     */
    private function latestRecords(): array
    {
        return collect(RecordHistory::recent(3, self::RECENCY_DAYS))
            ->map(fn (array $event): array => [
                'map' => $event['map'],
                'time' => $event['time'],
                'player' => $event['playerName'],
                'server' => $event['serverName'],
                'ago' => $event['setAt'] !== null ? $event['setAt']->diffForHumans() : '—',
            ])
            ->all();
    }

    /**
     * Top 3 servers by real Activity Score (App\Models\MostActiveServer, roadmap item 12),
     * podium-style per docs/homepage.md. Only servers with genuine activity (totalScore > 0)
     * qualify — a server with zero real engagement in the last 90 days has nothing to show,
     * same "empty rather than fake" rule as every other highlight here.
     *
     * @return list<array{rank: int, name: string, ip: string, port: string, players30d: int, players90d: int, lastActive: string}>
     */
    private function mostActiveServers(): array
    {
        return collect(MostActiveServer::scores())
            ->filter(fn (array $server): bool => $server['totalScore'] > 0)
            ->take(3)
            ->map(fn (array $server): array => [
                'rank' => $server['rank'],
                'name' => $server['name'],
                'ip' => $server['ip'],
                'port' => $server['port'],
                'players30d' => $server['players30d'],
                'players90d' => $server['players90d'],
                'lastActive' => $server['lastLapAt'] !== null ? $server['lastLapAt']->diffForHumans() : '—',
            ])
            ->values()
            ->all();
    }

    /**
     * Three sub-items — biggest PB improvement, largest per-map rank jump, and a new global
     * Top 10/Top 3 entry — all derived from this week's real laps. None of these need stored
     * historical snapshots: each re-runs the current ranking with one lap excluded and compares
     * to the real current state, exactly the technique docs/homepage.md describes.
     *
     * @param  Collection<int, LapTime>  $recentLaps
     * @param  array<int, array<string, mixed>>  $currentScoresByPlayer  keyed by playerId, see GlobalRanking::scores()
     * @return list<array{text: string}>
     */
    private function fastestImprovements(Collection $recentLaps, array $currentScoresByPlayer): array
    {
        if ($recentLaps->isEmpty()) {
            return [];
        }

        $items = [];
        $usedLapIds = [];

        // 1. Biggest PB improvement — delta vs. the lap immediately preceding it for the same
        // player+map+server. Matches how these rows actually came to exist historically (each
        // insert was itself a fresh improvement over whatever came right before it — see
        // docs/database.md); a negative "delta" (possible once the rebuilt webhook logs every
        // attempt, not just improvements) isn't a real improvement, so those are excluded.
        //
        // Gated on the RESULTING rank actually earning points (rank ≤50 — reusing
        // GlobalRanking::pointsForRank()'s existing cutoff rather than inventing a new
        // threshold). Without this, the metric is trivially gameable: submit a deliberately
        // terrible first lap on a map (nothing to beat yet, so it's automatically your "PB"),
        // then a merely-average one — the delta looks huge even though the actual result is
        // still nowhere near competitive. Confirmed on real data: an "81s improvement" landed a
        // player at map rank #70, which earns zero real points — not a genuine highlight.
        $biggestImprovement = $recentLaps
            ->map(function (LapTime $lap) use ($currentScoresByPlayer): ?array {
                $previous = LapTime::where('player_id', $lap->player_id)
                    ->where('map_id', $lap->map_id)
                    ->where('server_id', $lap->server_id)
                    ->where('id', '<', $lap->id)
                    ->orderByDesc('id')
                    ->first();

                if (! $previous) {
                    return null;
                }

                $delta = (float) $previous->time - (float) $lap->time;
                $rank = $this->currentMapRank($currentScoresByPlayer, $lap->player_id, $lap->map_id);

                if ($delta <= 0 || ! $rank || GlobalRanking::pointsForRank($rank) === 0) {
                    return null;
                }

                return ['lap' => $lap, 'delta' => $delta, 'rank' => $rank];
            })
            ->filter()
            ->sortByDesc('delta')
            ->first();

        if ($biggestImprovement) {
            $lap = $biggestImprovement['lap'];
            $usedLapIds[] = $lap->id;

            $items[] = [
                'text' => "{$lap->player->name} improved {$lap->map->label} by ".number_format($biggestImprovement['delta'], 2).'s'
                    ." (now #{$biggestImprovement['rank']}).",
            ];
        }

        // 2. Largest rank jump on a map — re-run that map's ranking with this lap excluded
        // (the player's standing immediately before it) vs. their real current rank. Prefers a
        // lap not already used above — a big enough improvement almost always causes the
        // biggest jump too, and showing the same event twice in one block reads as repetitive
        // rather than "3 different things happening." Only reuses it if it's genuinely the only
        // real candidate this week (see docs/decisions.md). Same points-earning gate as sub-item
        // 1 above, for the same reason — a jump from "very last" to "still not competitive"
        // isn't a real highlight just because the rank delta number is big.
        $jumpCandidates = $recentLaps
            ->map(function (LapTime $lap) use ($currentScoresByPlayer): ?array {
                $newRank = $this->currentMapRank($currentScoresByPlayer, $lap->player_id, $lap->map_id);
                $oldRank = GlobalRanking::mapRank($lap->map_id, $lap->player_id, excludeLapId: $lap->id);

                if (! $newRank || ! $oldRank || $oldRank <= $newRank || GlobalRanking::pointsForRank($newRank) === 0) {
                    return null;
                }

                return ['lap' => $lap, 'oldRank' => $oldRank, 'newRank' => $newRank, 'jump' => $oldRank - $newRank];
            })
            ->filter()
            ->sortByDesc('jump');

        $biggestJump = $jumpCandidates->reject(fn (array $c) => in_array($c['lap']->id, $usedLapIds, true))->first()
            ?? $jumpCandidates->first();

        if ($biggestJump) {
            $lap = $biggestJump['lap'];
            $usedLapIds[] = $lap->id;
            $items[] = [
                'text' => "{$lap->player->name} jumped from #{$biggestJump['oldRank']} to #{$biggestJump['newRank']} on {$lap->map->label}.",
            ];
        }

        // 3. New entry into the global Top 10 (or Top 3) — same before/after technique, applied
        // to Global Score instead of one map. Prefers a lap not already used above, same
        // distinct-story preference as sub-item 2. Also listed under Achievements' "first
        // appearance in Top 10/Top 3" — the same real event can legitimately surface in both
        // blocks if both are selected in the same load; not deduplicated across blocks, see
        // docs/decisions.md.
        $topEntryCandidates = $recentLaps
            ->unique('player_id')
            ->map(function (LapTime $lap) use ($currentScoresByPlayer): ?array {
                $current = $currentScoresByPlayer[$lap->player_id] ?? null;
                $before = $this->forPlayerBeforeLap($lap->player_id, $lap->id);
                $newRank = $current['rank'] ?? null;
                $oldRank = $before['rank'] ?? null;

                if (! $newRank || $newRank > 10 || ($oldRank !== null && $oldRank <= 10)) {
                    return null;
                }

                return ['lapId' => $lap->id, 'name' => $current['name'], 'newRank' => $newRank];
            })
            ->filter()
            ->sortBy('newRank');

        $newTopEntry = $topEntryCandidates->reject(fn (array $c) => in_array($c['lapId'], $usedLapIds, true))->first()
            ?? $topEntryCandidates->first();

        if ($newTopEntry) {
            $tier = $newTopEntry['newRank'] <= 3 ? 'Top 3' : 'Top 10';
            $items[] = ['text' => "{$newTopEntry['name']} entered the global {$tier} for the first time."];
        }

        return $items;
    }

    /**
     * A player's *current* (no exclusion) rank on one map, read out of an already-computed
     * `GlobalRanking::scores()` snapshot instead of calling `GlobalRanking::mapRank()` fresh
     * (PERF-01 fix, 2026-07-08) — every player's per-map ranks are already sitting in their
     * `perMap` breakdown from that one shared computation.
     *
     * @param  array<int, array<string, mixed>>  $currentScoresByPlayer  keyed by playerId, see GlobalRanking::scores()
     */
    private function currentMapRank(array $currentScoresByPlayer, int $playerId, int $mapId): ?int
    {
        $player = $currentScoresByPlayer[$playerId] ?? null;

        if (! $player) {
            return null;
        }

        foreach ($player['perMap'] as $mapEntry) {
            if ($mapEntry['mapId'] === $mapId) {
                return $mapEntry['rank'];
            }
        }

        return null;
    }

    /**
     * Memoized `GlobalRanking::forPlayer($playerId, excludeLapId: $lapId)` (PERF-01 fix,
     * 2026-07-08) — `fastestImprovements()`'s and `achievements()`'s Top 10 checks both dedupe
     * the same shared `$recentLaps` by `player_id`, so for any player with a recent lap that
     * appears in both blocks' candidate lists, both independently asked "what would this
     * player's rank have been without this exact lap" for the exact same (player, lap) pair —
     * `array_key_exists` (not `??=`) because a real `null` result (the player has no qualifying
     * lap without this one) must still count as cached, not be recomputed every time.
     *
     * @return array<string, mixed>|null
     */
    private function forPlayerBeforeLap(int $playerId, int $lapId): ?array
    {
        $key = "{$playerId}:{$lapId}";

        if (! array_key_exists($key, $this->forPlayerBeforeLapCache)) {
            $this->forPlayerBeforeLapCache[$key] = GlobalRanking::forPlayer($playerId, excludeLapId: $lapId);
        }

        return $this->forPlayerBeforeLapCache[$key];
    }

    /** @return list<array{type: string, name: string, ago: string}> */
    private function newContent(): array
    {
        $cutoff = now()->subDays(self::RECENCY_DAYS);

        $maps = Map::where('created_at', '>=', $cutoff)->get()
            ->map(fn (Map $map): array => ['type' => 'map', 'name' => $map->label, 'ago' => $map->created_at->diffForHumans(), 'at' => $map->created_at]);

        $servers = Server::where('created_at', '>=', $cutoff)->get()
            ->map(fn (Server $server): array => ['type' => 'server', 'name' => $server->name, 'ago' => $server->created_at->diffForHumans(), 'at' => $server->created_at]);

        return $maps->concat($servers)
            ->sortByDesc('at')
            ->map(fn (array $entry): array => ['type' => $entry['type'], 'name' => $entry['name'], 'ago' => $entry['ago']])
            ->values()
            ->all();
    }

    /**
     * First-ever course records, lap-count milestones, and first Top 10/Top 3 appearances, for
     * players active this week — in that priority order per docs/homepage.md.
     *
     * @param  Collection<int, LapTime>  $recentLaps
     * @param  array<int, array<string, mixed>>  $currentScoresByPlayer  keyed by playerId, see GlobalRanking::scores()
     * @return list<array{player: string, note: string}>
     */
    private function achievements(Collection $recentLaps, array $currentScoresByPlayer): array
    {
        $recentLaps = $recentLaps->unique('player_id');

        if ($recentLaps->isEmpty()) {
            return [];
        }

        $items = [];

        // 1. First-ever course record — App\Models\RecordHistory's chronological replay tells us
        // exactly which lap was each player's first, so this checks whether one of this week's
        // laps *is* that lap (real data added 2026-07-06, previously skipped for lack of this).
        $firstRecordByPlayer = collect(RecordHistory::events())->unique('playerId')->keyBy('playerId');

        foreach ($recentLaps as $lap) {
            if (count($items) >= 3) {
                break;
            }

            $firstRecord = $firstRecordByPlayer[$lap->player_id] ?? null;

            if ($firstRecord && $firstRecord['lapId'] === $lap->id) {
                $items[] = ['player' => $lap->player->name, 'note' => "set their first-ever course record on {$firstRecord['map']}"];
            }
        }

        // 2. Lap-count milestones, calibrated to this project's real (small) scale — the most
        // laps any real player has ever raced is in the dozens as of 2026-07-06, not the
        // thousands a generic "1,000 laps" milestone would assume. See docs/decisions.md.
        $milestones = [10, 25, 50, 100, 250, 500, 1000];

        foreach ($recentLaps as $lap) {
            if (count($items) >= 3) {
                break;
            }

            $totalNow = LapTime::where('player_id', $lap->player_id)->whereHas('server')->count();
            $totalBefore = $totalNow - 1;

            $crossed = collect($milestones)->first(fn (int $m): bool => $totalBefore < $m && $totalNow >= $m);

            if ($crossed) {
                $items[] = ['player' => $lap->player->name, 'note' => "crossed {$crossed} total laps"];
            }
        }

        // 3. First appearance in the global Top 10/Top 3.
        foreach ($recentLaps as $lap) {
            if (count($items) >= 3) {
                break;
            }

            $current = $currentScoresByPlayer[$lap->player_id] ?? null;
            $before = $this->forPlayerBeforeLap($lap->player_id, $lap->id);
            $newRank = $current['rank'] ?? null;
            $oldRank = $before['rank'] ?? null;

            if ($newRank && $newRank <= 10 && ($oldRank === null || $oldRank > 10)) {
                $tier = $newRank <= 3 ? 'Top 3' : 'Top 10';
                $items[] = ['player' => $current['name'], 'note' => "first appearance in the global {$tier}"];
            }
        }

        return array_slice($items, 0, 3);
    }

    /** @return array{totalLaps: int, activePlayers30d: int, activePlayers90d: int, activeServers30d: int, activeServers90d: int, mapsToday: int, mapsThisWeek: int} */
    private function liveStats(): array
    {
        $activeLaps = fn () => LapTime::whereHas('server');

        return [
            'totalLaps' => LapTime::count(),
            'activePlayers30d' => $activeLaps()->where('created_at', '>=', now()->subDays(30))->distinct('player_id')->count('player_id'),
            'activePlayers90d' => $activeLaps()->where('created_at', '>=', now()->subDays(90))->distinct('player_id')->count('player_id'),
            'activeServers30d' => $activeLaps()->where('created_at', '>=', now()->subDays(30))->distinct('server_id')->count('server_id'),
            'activeServers90d' => $activeLaps()->where('created_at', '>=', now()->subDays(90))->distinct('server_id')->count('server_id'),
            'mapsToday' => $activeLaps()->whereDate('created_at', now()->toDateString())->distinct('map_id')->count('map_id'),
            'mapsThisWeek' => $activeLaps()->where('created_at', '>=', now()->subDays(7))->distinct('map_id')->count('map_id'),
        ];
    }

    public function render()
    {
        return view('livewire.home');
    }
}
