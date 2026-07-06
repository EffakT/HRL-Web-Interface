<?php

namespace App\Models;

use Carbon\Carbon;

/**
 * Historical record-breaking-event derivation — see docs/roadmap.md item 13 and
 * docs/homepage.md's "Latest / Current Records" highlight for the problem this solves.
 *
 * Not an Eloquent model (no table backs it): a pure, stateless calculation over real LapTime
 * data, computed fresh on every call — same "derive, don't cache" precedent as GlobalRanking
 * and MostActiveServer.
 *
 * Genuinely different from those two: `GlobalRanking`/`MostActiveServer` only ever need
 * "current state" (optionally with one lap excluded). This needs an actual point-in-time
 * replay — for each map, walk every lap in chronological order and note every point where the
 * map's fastest time changed hands. There's no shortcut via a single-lap-exclusion comparison
 * for "when was this record actually set."
 */
class RecordHistory
{
    /**
     * Every record-breaking event, across all maps (or one map via `$mapId`), in chronological
     * order — oldest first. A lap counts as an event if it's strictly faster than the fastest
     * time recorded on that map before it (ties don't count, matching this app's established
     * "earliest lap wins a tie" convention everywhere else). A map's very first-ever lap always
     * counts too (trivially becomes the record; `previousTime` is null for these).
     *
     * @return list<array{
     *     mapId: int, map: string, lapId: int, playerId: int, playerName: string,
     *     serverId: int, serverName: string, time: string, timeRaw: float,
     *     previousTimeRaw: ?float, setAt: ?Carbon,
     * }>
     */
    public static function events(?int $mapId = null): array
    {
        // Laps belonging to soft-deleted servers are treated as nonexistent, matching every
        // other real leaderboard read in this app. Chronological order (created_at, then id as
        // the deterministic fallback for historical rows sharing a day-precision timestamp) is
        // load-bearing here — this is what makes the replay below correct.
        $laps = LapTime::query()
            ->when($mapId, fn ($query) => $query->where('map_id', $mapId))
            ->whereHas('server')
            ->with(['player', 'map', 'server'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $currentRecord = [];
        $events = [];

        foreach ($laps as $lap) {
            $time = (float) $lap->time;
            $previous = $currentRecord[$lap->map_id] ?? null;

            if ($previous !== null && $time >= $previous) {
                continue;
            }

            $events[] = [
                'mapId' => $lap->map_id,
                'map' => $lap->map->label,
                'lapId' => $lap->id,
                'playerId' => $lap->player_id,
                'playerName' => $lap->player->name,
                'serverId' => $lap->server_id,
                'serverName' => $lap->server->name,
                'time' => $lap->formattedTime(),
                'timeRaw' => $time,
                'previousTimeRaw' => $previous,
                'setAt' => $lap->created_at,
            ];

            $currentRecord[$lap->map_id] = $time;
        }

        return $events;
    }

    /**
     * The most recent events overall (any map), newest first — for Homepage's "Latest / Current
     * Records" highlight. `$withinDays` filters to a recency window; omit for all-time.
     *
     * @return list<array{
     *     mapId: int, map: string, lapId: int, playerId: int, playerName: string,
     *     serverId: int, serverName: string, time: string, timeRaw: float,
     *     previousTimeRaw: ?float, setAt: ?Carbon,
     * }>
     */
    public static function recent(int $limit = 3, ?int $withinDays = null): array
    {
        $cutoff = $withinDays !== null ? now()->subDays($withinDays) : null;

        return collect(self::events())
            ->filter(fn (array $event): bool => $cutoff === null || ($event['setAt'] !== null && $event['setAt']->gte($cutoff)))
            ->sortByDesc(fn (array $event): int => $event['setAt'] !== null ? $event['setAt']->timestamp : 0)
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * A player's first-ever record-breaking event (their first course record on any map), or
     * null if they've never held one. Powers Player Achievements' "first record" sub-item.
     */
    public static function firstRecordFor(int $playerId): ?array
    {
        return collect(self::events())
            ->where('playerId', $playerId)
            ->sortBy(fn (array $event): int => $event['setAt'] !== null ? $event['setAt']->timestamp : 0)
            ->first();
    }
}
