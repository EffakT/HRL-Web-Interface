<?php

namespace App\Jobs;

use App\Events\LapSubmitted;
use App\Events\LeaderboardUpdated;
use App\Helpers\GameServerQuery;
use App\Models\LapTime;
use App\Models\LapTimeSplit;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
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
     * @param  array{map_name: string, player_hash: string, player_name: string, player_time: float, race_type: int, splits: array<int, array{checkpoint_id: int, duration: float, startTime: float|null, endTime: float|null}>|null}  $data
     * @param  ?array<string, string>  $liveQueryResponse  Already-fetched UDP query response (SEC-01's
     *                                                     LapSubmissionVerifier queries the same ip:port
     *                                                     before this job runs) — reused here instead of
     *                                                     querying the server a second time. Null when
     *                                                     verification is disabled/didn't run, or didn't
     *                                                     get a response — resolveHostname() falls back
     *                                                     to querying itself in that case, same as before.
     */
    public function __construct(
        private readonly string $ip,
        private readonly int $port,
        private readonly array $data,
        private readonly ?array $liveQueryResponse = null,
    ) {}

    /**
     * @return array{success: bool, isNewRecord: bool, lapTime: float, bestTime: float, leaderboardPosition: array{position: int, total: int, topTime?: float, difference?: float}}
     */
    public function handle(GameServerQuery $query): array
    {
        $hostname = $this->resolveHostname($query);
        $mapLabel = $this->mapLabel($this->data['map_name'], $this->data['race_type']);

        $result = DB::transaction(function () use ($hostname, $mapLabel): array {
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
            $lapTime = LapTime::create([
                'server_id' => $server->id,
                'map_id' => $map->id,
                'player_id' => $player->id,
                'time' => $newTime,
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
        });

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
     * A failed live query no longer aborts the whole submission (the old app dropped the lap
     * entirely if the UDP query failed) — a temporary game-server query hiccup shouldn't
     * silently discard real lap data. A brand-new, never-before-seen server just gets a
     * placeholder name until a later successful query updates it.
     */
    private function resolveHostname(GameServerQuery $query): ?string
    {
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
