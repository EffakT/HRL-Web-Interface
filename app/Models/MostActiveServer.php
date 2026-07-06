<?php

namespace App\Models;

use Carbon\Carbon;

/**
 * Most Active Server scoring — see docs/most-active-server.md for the full spec.
 *
 * Not an Eloquent model (no table backs it): a pure, stateless calculation over real LapTime
 * data, computed fresh on every call — same "derive, don't cache" precedent as GlobalRanking.
 * The spec calls for "periodic recalculation," implying a scheduled job may eventually be
 * needed, but at real current scale (3 active servers) a live query is trivially fast — no
 * job has been built; revisit only if profiling at real scale says otherwise (see
 * docs/performance.md, docs/decisions.md).
 */
class MostActiveServer
{
    private const WINDOW_DAYS = 90;

    /**
     * Activity Score + tie-break for every active (non-soft-deleted) server, ranked.
     *
     * Returns a plain array, not a Collection — same precedent as GlobalRanking::scores(),
     * which sidesteps Collection's non-covariant TValue generic being unable to hold this
     * dynamically-shaped array without a PHPStan mismatch.
     *
     * @return array<int, array{
     *     serverId: int, name: string, ip: string, port: string, rank: int, activityScore: int,
     *     recencyBonus: int, totalScore: int, uniquePlayers: int, mapsPlayed: int,
     *     validLaps: int, players30d: int, players90d: int, lastLapAt: ?Carbon,
     * }>
     */
    public static function scores(): array
    {
        $windowStart = now()->subDays(self::WINDOW_DAYS);

        $servers = Server::all()->map(function (Server $server) use ($windowStart): array {
            // Every recorded lap is treated as valid — no anti-cheat/validity mechanism exists
            // yet, explicitly deferred per docs/most-active-server.md. "Valid Laps" here means
            // "Laps" until that exists.
            $windowLaps = LapTime::where('server_id', $server->id)
                ->where('created_at', '>=', $windowStart)
                ->get(['player_id', 'map_id']);

            $uniquePlayers = $windowLaps->pluck('player_id')->unique()->count();
            $mapsPlayed = $windowLaps->pluck('map_id')->unique()->count();
            // Valid Laps = distinct (player, map) participations, not raw lap count — prevents
            // one player grinding the same map from inflating the score unboundedly.
            $validLaps = $windowLaps->map(fn (LapTime $lap): string => "{$lap->player_id}:{$lap->map_id}")->unique()->count();

            $activityScore = ($uniquePlayers * 10) + ($validLaps * 1) + ($mapsPlayed * 20);

            $latestLap = LapTime::where('server_id', $server->id)
                ->orderByDesc('created_at')
                ->first();
            $lastLapAt = $latestLap?->created_at;
            $lastLapTimestamp = $lastLapAt !== null ? $lastLapAt->timestamp : 0;

            $recencyBonus = match (true) {
                $lastLapAt && $lastLapAt->gte(now()->subDays(7)) => 100,
                $lastLapAt && $lastLapAt->gte(now()->subDays(30)) => 50,
                $lastLapAt && $lastLapAt->gte(now()->subDays(90)) => 20,
                default => 0,
            };

            // Display-only 30d/90d unique-player counts (Homepage's Most Active Server
            // highlight) — independent of the score's own 90-day base window, per
            // docs/most-active-server.md.
            $players30d = LapTime::where('server_id', $server->id)
                ->where('created_at', '>=', now()->subDays(30))
                ->distinct('player_id')
                ->count('player_id');
            $players90d = LapTime::where('server_id', $server->id)
                ->where('created_at', '>=', now()->subDays(90))
                ->distinct('player_id')
                ->count('player_id');

            return [
                'serverId' => $server->id,
                'name' => $server->name,
                'ip' => $server->ip,
                'port' => $server->port,
                'rank' => 0,
                'activityScore' => $activityScore,
                'recencyBonus' => $recencyBonus,
                'totalScore' => $activityScore + $recencyBonus,
                'uniquePlayers' => $uniquePlayers,
                'mapsPlayed' => $mapsPlayed,
                'validLaps' => $validLaps,
                'players30d' => $players30d,
                'players90d' => $players90d,
                'lastLapAt' => $lastLapAt,
                // Plain, always-int tie-break key (0 if no lap ever) — kept alongside the real
                // ?Carbon field above so the tie-break sort below never has to null-check.
                'lastLapTimestamp' => $lastLapTimestamp,
            ];
        })->all();

        // Tie-break, in order, per docs/most-active-server.md: more unique players, more maps
        // played, more valid laps, most recent activity.
        usort($servers, fn (array $a, array $b): int => $b['totalScore'] <=> $a['totalScore']
            ?: $b['uniquePlayers'] <=> $a['uniquePlayers']
            ?: $b['mapsPlayed'] <=> $a['mapsPlayed']
            ?: $b['validLaps'] <=> $a['validLaps']
            ?: $b['lastLapTimestamp'] <=> $a['lastLapTimestamp']);

        return collect($servers)->values()->map(function (array $server, int $index): array {
            $server['rank'] = $index + 1;
            unset($server['lastLapTimestamp']);

            return $server;
        })->all();
    }

    public static function forServer(int $serverId): ?array
    {
        return collect(static::scores())->firstWhere('serverId', $serverId);
    }
}
