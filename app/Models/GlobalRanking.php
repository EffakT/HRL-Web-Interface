<?php

namespace App\Models;

use Carbon\Carbon;

/**
 * Global Player Ranking calculator — see docs/global-ranking.md for the full spec.
 *
 * Not an Eloquent model (no table backs it): a pure, stateless calculation over real LapTime
 * data, computed fresh on every call rather than stored — matches this project's "derive,
 * don't cache" default (see docs/database.md, docs/performance.md). Revisit only if profiling
 * shows it's actually slow at real scale.
 *
 * Lives under App\Models (not a new app/Services folder) following the precedent set by
 * LapTimeSplit::compare() — a static, heavily-documented calculation helper placed alongside
 * the models it reads, per docs/coding-standards.md's "don't create new base folders without
 * approval" rule.
 */
class GlobalRanking
{
    /** @var array<int, int> Fixed points for ranks 1-10, per docs/global-ranking.md. */
    private const array TOP_10_POINTS = [
        1 => 100, 2 => 95, 3 => 90, 4 => 86, 5 => 82,
        6 => 79, 7 => 76, 8 => 73, 9 => 70, 10 => 68,
    ];

    /** Two-band linear interpolation, 11–25 then 26–50, per docs/global-ranking.md. */
    public static function pointsForRank(int $rank): int
    {
        return match (true) {
            $rank <= 10 => self::TOP_10_POINTS[$rank],
            $rank <= 25 => (int) round(68 + (40 - 68) * ($rank - 10) / (25 - 10)),
            $rank <= 50 => (int) round(40 + (10 - 40) * ($rank - 25) / (50 - 25)),
            default => 0,
        };
    }

    /**
     * Turns every player's total per-map points into their displayed Global/Server Score, per
     * `config('ranking.global_score_variant')` — see config/ranking.php for what each variant
     * means and docs/decisions.md for the real-data example that prompted having both.
     *
     * `average` needs the whole population at once (not just one player at a time): a naive
     * average (points ÷ maps played) lets a single perfect map trivially outscore broad
     * excellence — a player who's raced only 1 map and got rank 1 on it averages a flat 100.0,
     * beating a player who holds the record on 6 of 9 maps (97 average). Regularized with a
     * Bayesian/weighted average (IMDB's "weighted rating" formula): a low-sample average is
     * pulled toward the overall mean by `config('ranking.average_confidence_maps')` "virtual"
     * maps of that mean, fading out as a player races more maps of their own.
     *
     * @param  array<int, array{
     *     playerId: int, name: string, rank: int, score: int, mapsPlayed: int,
     *     firstPlaces: int, top3: int, top10: int, fastestLap: float, firstAchievedAt: ?Carbon,
     *     perMap: non-empty-list<array{
     *         mapId: int, map: string, serverId: int, server: string,
     *         rank: int, points: int, time: string, lapId: int, setAt: ?Carbon,
     *     }>
     * }>  $players  keyed by player id
     * @return array<int, array{
     *     playerId: int, name: string, rank: int, score: int, mapsPlayed: int,
     *     firstPlaces: int, top3: int, top10: int, fastestLap: float, firstAchievedAt: ?Carbon,
     *     perMap: non-empty-list<array{
     *         mapId: int, map: string, serverId: int, server: string,
     *         rank: int, points: int, time: string, lapId: int, setAt: ?Carbon,
     *     }>
     * }> same shape, 'score' replaced
     */
    private static function applyScoreVariant(array $players): array
    {
        if (config('ranking.global_score_variant', 'sum') !== 'average' || $players === []) {
            return $players;
        }

        $confidenceMaps = (int) config('ranking.average_confidence_maps', 2);

        $rawAverages = array_map(
            fn (array $player): float => $player['score'] / $player['mapsPlayed'],
            $players
        );

        $overallAverage = array_sum($rawAverages) / count($rawAverages);

        foreach ($players as $playerId => $player) {
            $weight = $player['mapsPlayed'] / ($player['mapsPlayed'] + $confidenceMaps);
            $weightedAverage = ($weight * $rawAverages[$playerId]) + ((1 - $weight) * $overallAverage);

            $players[$playerId]['score'] = (int) round($weightedAverage);
        }

        return $players;
    }

    /**
     * Global Score for every player with at least one real lap, ranked and fully tie-broken
     * per docs/global-ranking.md. Pass `$serverId` to compute the "Server Score" variant
     * instead — the same formula applied to one server's nested per-map leaderboards only.
     *
     * Returns a plain array, not a Collection — same precedent as LapTimeSplit::compare(),
     * which sidesteps Collection's non-covariant TValue generic being unable to hold this
     * dynamically-shaped array without a PHPStan mismatch.
     *
     * @return array<int, array{
     *     playerId: int, name: string, rank: int, score: int, mapsPlayed: int,
     *     firstPlaces: int, top3: int, top10: int, fastestLap: float, firstAchievedAt: ?Carbon,
     *     perMap: non-empty-list<array{
     *         mapId: int, map: string, serverId: int, server: string,
     *         rank: int, points: int, time: string, lapId: int, setAt: ?Carbon,
     *     }>
     * }>
     */
    public static function scores(?int $serverId = null, ?int $excludeLapId = null): array
    {
        // Laps belonging to soft-deleted servers are treated as nonexistent, matching every
        // other real leaderboard read in this app (see MapLeaderboard, MapList, ServerShow).
        // `$excludeLapId` supports "what would the ranking have looked like without this one
        // lap" comparisons (Homepage's Fastest Improvements / Achievements highlights) without
        // needing any stored historical snapshots — see docs/homepage.md.
        $laps = LapTime::query()
            ->when($serverId, fn ($query) => $query->where('server_id', $serverId))
            ->when($excludeLapId, fn ($query) => $query->where('id', '!=', $excludeLapId))
            ->whereHas('server')
            ->with(['player', 'map', 'server'])
            ->orderBy('time')->oldest()
            ->orderBy('id')
            ->get();

        $players = [];

        foreach ($laps->groupBy('map_id') as $mapLaps) {
            // Ties go to the earliest lap, id as final deterministic fallback — same tie-break
            // as MapLeaderboard's per-map ranking, extended here to every map at once.
            $bestPerPlayer = $mapLaps->unique('player_id')->values();

            foreach ($bestPerPlayer as $index => $lap) {
                $rank = $index + 1;
                $points = self::pointsForRank($rank);
                $time = (float) $lap->time;

                $perMapEntry = [
                    'mapId' => $lap->map_id,
                    'map' => $lap->map->label,
                    'serverId' => $lap->server_id,
                    'server' => $lap->server->name,
                    'rank' => $rank,
                    'points' => $points,
                    'time' => $lap->formattedTime(),
                    'lapId' => $lap->id,
                    'setAt' => $lap->created_at,
                ];

                if (! isset($players[$lap->player_id])) {
                    $players[$lap->player_id] = [
                        'playerId' => $lap->player_id,
                        'name' => $lap->player->name,
                        'rank' => 0,
                        'score' => $points,
                        'mapsPlayed' => 1,
                        'firstPlaces' => $rank === 1 ? 1 : 0,
                        'top3' => $rank <= 3 ? 1 : 0,
                        'top10' => $rank <= 10 ? 1 : 0,
                        'fastestLap' => $time,
                        'firstAchievedAt' => $lap->created_at,
                        'perMap' => [$perMapEntry],
                    ];

                    continue;
                }

                if ($time < $players[$lap->player_id]['fastestLap']) {
                    $players[$lap->player_id]['fastestLap'] = $time;
                }

                if ($lap->created_at && (! $players[$lap->player_id]['firstAchievedAt'] || $lap->created_at->lt($players[$lap->player_id]['firstAchievedAt']))) {
                    $players[$lap->player_id]['firstAchievedAt'] = $lap->created_at;
                }

                $players[$lap->player_id]['score'] += $points;
                $players[$lap->player_id]['mapsPlayed']++;
                $players[$lap->player_id]['firstPlaces'] += $rank === 1 ? 1 : 0;
                $players[$lap->player_id]['top3'] += $rank <= 3 ? 1 : 0;
                $players[$lap->player_id]['top10'] += $rank <= 10 ? 1 : 0;
                $players[$lap->player_id]['perMap'][] = $perMapEntry;
            }
        }

        // Apply the configured score variant (sum vs. average) before ranking/tie-break, so
        // both reflect whichever definition of "score" is currently active — not just display.
        $ranked = array_values(self::applyScoreVariant($players));

        // Global Score tie-break, in order, per docs/global-ranking.md: most 1st places, most
        // top-3s, most top-10s, fastest single lap, earliest achievement date. Genuinely tied
        // after all five stays possible — the spec defines no further tiebreaker for that case.
        usort($ranked, fn (array $a, array $b): int => $b['score'] <=> $a['score']
            ?: $b['firstPlaces'] <=> $a['firstPlaces']
            ?: $b['top3'] <=> $a['top3']
            ?: $b['top10'] <=> $a['top10']
            ?: $a['fastestLap'] <=> $b['fastestLap']
            ?: $a['firstAchievedAt'] <=> $b['firstAchievedAt']);

        return collect($ranked)->values()->map(function (array $player, int $index): array {
            $player['rank'] = $index + 1;

            return $player;
        })->all();
    }

    /**
     * @return array{
     *     playerId: int, name: string, rank: int, score: int, mapsPlayed: int,
     *     firstPlaces: int, top3: int, top10: int, fastestLap: float, firstAchievedAt: ?Carbon,
     *     perMap: non-empty-list<array{
     *         mapId: int, map: string, serverId: int, server: string,
     *         rank: int, points: int, time: string, lapId: int, setAt: ?Carbon,
     *     }>
     * }|null
     */
    public static function forPlayer(int $playerId, ?int $serverId = null, ?int $excludeLapId = null): ?array
    {
        return collect(static::scores($serverId, $excludeLapId))->firstWhere('playerId', $playerId);
    }

    /**
     * One player's rank on a single map — cheaper than `scores()` when only one map's ranking
     * is needed (e.g. Homepage's "rank jump" highlight, checked per recent lap). `$excludeLapId`
     * supports the same before/after comparison as `scores()`, scoped to one map instead of
     * every map at once. Null if the player has no qualifying lap on this map (at all, or after
     * exclusion).
     */
    public static function mapRank(int $mapId, int $playerId, ?int $serverId = null, ?int $excludeLapId = null): ?int
    {
        $laps = LapTime::where('map_id', $mapId)
            ->when($serverId, fn ($query) => $query->where('server_id', $serverId))
            ->when($excludeLapId, fn ($query) => $query->where('id', '!=', $excludeLapId))
            ->whereHas('server')
            ->orderBy('time')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $bestPerPlayer = $laps->unique('player_id')->values();
        $index = $bestPerPlayer->search(fn (LapTime $lap): bool => $lap->player_id === $playerId);

        return $index === false ? null : $index + 1;
    }

    /**
     * The full ranked leaderboard for one map — every player's single best lap, ranked, with
     * the same tie-break used everywhere else (earliest lap wins a tie). `$serverId` scopes to
     * one server's nested leaderboard instead of the global (all-servers) one.
     *
     * A third occurrence of "rank every player's best lap on one map" (after `MapLeaderboard`
     * and `ServerMapLeaderboard`'s own inline, display-formatted versions) — per
     * docs/coding-standards.md's "extract on the second genuine duplicate" rule, this gives the
     * API (docs/api.md) its own canonical, testable, raw-data source rather than a fourth ad-hoc
     * copy. Deliberately not shared with those two Livewire components themselves: their
     * versions are tightly coupled to UI-specific formatting (zero-padded rank strings, gap
     * display strings), which the API has no use for.
     *
     * @return list<array{
     *     rank: int, lapId: int, playerId: int, playerName: string,
     *     serverId: int, serverName: string, timeRaw: float, time: string,
     *     gapRaw: ?float, setAt: ?Carbon,
     *     splits: list<array{checkpoint_id: int, duration: float}>,
     * }>
     */
    public static function mapLeaderboard(int $mapId, ?int $serverId = null): array
    {
        $laps = LapTime::where('map_id', $mapId)
            ->when($serverId, fn ($query) => $query->where('server_id', $serverId))
            ->whereHas('server')
            ->with(['player', 'server', 'splits'])
            ->orderBy('time')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $bestPerPlayer = $laps->unique('player_id')->values();
        $topLap = $bestPerPlayer->first();
        $topTime = $topLap !== null ? (float) $topLap->time : null;

        return array_values($bestPerPlayer
            ->map(function (LapTime $lap, int $index) use ($topTime): array {
                $time = (float) $lap->time;

                return [
                    'rank' => $index + 1,
                    'lapId' => $lap->id,
                    'playerId' => $lap->player_id,
                    'playerName' => $lap->player->name,
                    'serverId' => $lap->server_id,
                    'serverName' => $lap->server->name,
                    'timeRaw' => $time,
                    'time' => $lap->formattedTime(),
                    'gapRaw' => $topTime !== null ? round($time - $topTime, 3) : null,
                    'setAt' => $lap->created_at,
                    // Sparse real coverage (~4% of laps have splits — see docs/database.md), so
                    // an empty array here is the common case, same as LapTimeResource's own
                    // `splits` key on the single-lap endpoint.
                    'splits' => array_values($lap->splits->map(fn (LapTimeSplit $split): array => [
                        'checkpoint_id' => $split->checkpoint_id,
                        'duration' => $split->duration,
                    ])->all()),
                ];
            })
            ->all());
    }
}
