<?php

namespace App\Models;

/**
 * Shared player-profile assembly — extracted from `PlayerShow` once `Servers\ServerPlayerShow`
 * needed the identical thing scoped to one server (per docs/coding-standards.md's "extract on
 * the second genuine duplicate" rule). Not an Eloquent model (no table): a stateless calculation
 * over a `GlobalRanking::forPlayer()` result, same non-Eloquent-calculator precedent as
 * `GlobalRanking`/`MostActiveServer`/`RecordHistory`.
 *
 * Scoping to one server is entirely driven by the `$ranking` array already being server-scoped
 * (pass `GlobalRanking::forPlayer($playerId, $serverId)` instead of the unscoped call) plus an
 * explicit `$serverId` here for the lap queries this class runs itself (Stats Card/Recent Laps).
 * Course-record comparisons (`recordHolder`/`recordTime`) are deliberately **never** scoped to
 * one server — a "record" always means the fastest lap anywhere, matching every other real
 * record reference in this app (see docs/decisions.md).
 */
class PlayerProfile
{
    /**
     * @param  array<string, mixed>|null  $ranking  A `GlobalRanking::forPlayer()` result (global or server-scoped) — null if this player has no qualifying lap in scope at all
     * @return array{
     *     laps: array<int, array<string, mixed>>,
     *     performanceKeys: array<int, int>,
     *     recentLapKeys: array<int, int>,
     *     statsCard: array{numRecords: int, top3Finishes: int, mapsCompleted: int, totalValidLaps: int, firstSeen: string, lastActive: string},
     *     achievements: array<int, string>,
     * }
     */
    public static function build(Player $player, ?array $ranking, ?int $serverId = null): array
    {
        $perMap = collect($ranking['perMap'] ?? []);

        // Course record (global, across active servers) for every map this player has raced —
        // same tie-break as every other real leaderboard read (earliest lap wins a time tie).
        // Never scoped to $serverId — see class docblock.
        $recordsByMap = LapTime::whereIn('map_id', $perMap->pluck('mapId'))
            ->whereHas('server')
            ->with('player')
            ->orderBy('time')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('map_id')
            ->map(fn ($group) => $group->first());

        // "Performance by Map" — this player's single best lap on each map in scope (already
        // scoped to one server if $ranking came from a server-scoped forPlayer() call, since its
        // perMap only ever contains maps raced on that server).
        $laps = $perMap->map(function (array $row) use ($recordsByMap): array {
            $record = $recordsByMap[$row['mapId']] ?? null;

            return [
                'mapId' => $row['mapId'],
                'lapId' => $row['lapId'],
                'recordLapId' => $record?->id,
                'map' => $row['map'],
                'server' => $row['server'],
                'time' => $row['time'],
                'date' => $row['setAt']?->diffForHumans() ?? '—',
                'dateExact' => $row['setAt'] ? $row['setAt']->format('d M Y, H:i').' '.$row['setAt']->format('T') : '—',
                'recordHolder' => $record?->player->name ?? '—',
                'recordTime' => $record ? LapTime::formatSeconds($record->time) : '—',
                'mapRank' => $row['rank'],
                'points' => $row['points'],
            ];
        })->all();
        $performanceKeys = array_keys($laps);

        // All of this player's real laps in scope (every attempt, not just per-map bests) —
        // drives the Stats Card, which needs full lap volume, not just PB rows.
        $allLaps = $player->lapTimes()
            ->whereHas('server')
            ->with('server')
            ->when($serverId !== null, fn ($query) => $query->where('server_id', $serverId))
            ->get();

        // Recent Laps — the player's actual last 10 attempts in scope, chronological (not the
        // per-map-PB ordering "Performance by Map" uses). Appended to $laps under a new key when
        // not already one of the per-map bests, so the shared Lap Detail modal can address them
        // via openLap() regardless of which table opened them.
        $recentLaps = LapTime::where('player_id', $player->id)
            ->whereHas('server')
            ->with(['map', 'server'])
            ->when($serverId !== null, fn ($query) => $query->where('server_id', $serverId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $lapIdToKey = collect($laps)->mapWithKeys(fn (array $lap, int $key) => [$lap['lapId'] => $key]);

        $recentLapKeys = $recentLaps
            ->map(function (LapTime $lap) use (&$laps, $lapIdToKey, $recordsByMap): int {
                if ($lapIdToKey->has($lap->id)) {
                    return $lapIdToKey[$lap->id];
                }

                $record = $recordsByMap[$lap->map_id] ?? null;
                $key = count($laps);

                $laps[$key] = [
                    'mapId' => $lap->map_id,
                    'lapId' => $lap->id,
                    'recordLapId' => $record?->id,
                    'map' => $lap->map->label,
                    'server' => $lap->server->name,
                    'time' => $lap->formattedTime(),
                    'date' => $lap->created_at?->diffForHumans() ?? '—',
                    'dateExact' => $lap->created_at ? $lap->created_at->format('d M Y, H:i').' '.$lap->created_at->format('T') : '—',
                    'recordHolder' => $record?->player->name ?? '—',
                    'recordTime' => $record ? LapTime::formatSeconds($record->time) : '—',
                    'mapRank' => null,
                    'points' => null,
                ];
                $lapIdToKey[$lap->id] = $key;

                return $key;
            })
            ->all();

        $statsCard = [
            'numRecords' => $ranking['firstPlaces'] ?? 0,
            'top3Finishes' => $ranking['top3'] ?? 0,
            'mapsCompleted' => $ranking['mapsPlayed'] ?? 0,
            'totalValidLaps' => $allLaps->count(),
            'firstSeen' => $allLaps->min('created_at')?->format('d M Y') ?? '—',
            'lastActive' => $allLaps->max('created_at')?->diffForHumans() ?? '—',
        ];

        // Best Performance — curated, not a raw top-3-fastest-laps list (raw times aren't
        // comparable across maps of different lengths, see docs/decisions.md).
        $recordMaps = $perMap->where('rank', 1)->pluck('map');
        $achievements = array_filter([
            $recordMaps->isNotEmpty()
                ? 'Holds the course record on '.$recordMaps->join(', ', ' and ').'.'
                : null,
            $perMap->isNotEmpty()
                ? "Top 3 finish on {$statsCard['top3Finishes']} of {$statsCard['mapsCompleted']} maps raced."
                : null,
        ]) ?: ['No standout finishes yet — keep racing to climb the leaderboard.'];

        return [
            'laps' => $laps,
            'performanceKeys' => $performanceKeys,
            'recentLapKeys' => $recentLapKeys,
            'statsCard' => $statsCard,
            'achievements' => array_values($achievements),
        ];
    }
}
