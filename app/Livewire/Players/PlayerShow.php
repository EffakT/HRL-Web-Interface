<?php

namespace App\Livewire\Players;

use App\Livewire\Concerns\HasLapDetailModal;
use App\Livewire\Concerns\HasRecordVsRunnerUpReference;
use App\Models\GlobalRanking;
use App\Models\LapTime;
use App\Models\LapTimeSplit;
use App\Models\Player;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Player', 'active' => 'players'])]
class PlayerShow extends Component
{
    use HasLapDetailModal, HasRecordVsRunnerUpReference;

    public string $playerId;

    public string $playerName;

    public array $playerInfo = [];

    public array $statsCard = [];

    public array $achievements = [];

    public array $laps = [];

    /** Keys into $laps for the Performance by Map table, in per-map order. */
    public array $performanceKeys = [];

    /** Keys into $laps for the Recent Laps feed, in reverse-chronological order. */
    public array $recentLapKeys = [];

    public array $favServers = [];

    public function mount(string $playerId): void
    {
        $player = Player::findOrFail($playerId);

        $this->playerId = $playerId;
        $this->playerName = $player->name;

        $ranking = GlobalRanking::forPlayer($player->id);
        $perMap = collect($ranking['perMap'] ?? []);

        $this->playerInfo = [
            'globalRank' => $ranking['rank'] ?? '—',
            'globalScore' => $ranking['score'] ?? 0,
        ];

        // Map record (global, across active servers) for every map this player has raced —
        // same tie-break as every other real leaderboard read (earliest lap wins a time tie).
        $recordsByMap = LapTime::whereIn('map_id', $perMap->pluck('mapId'))
            ->whereHas('server')
            ->with('player')
            ->orderBy('time')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('map_id')
            ->map(fn ($group) => $group->first());

        // "Performance by Map" — this player's single best lap on each map, which is exactly
        // what GlobalRanking::forPlayer()'s perMap breakdown already is. Feeds the shared Lap
        // Detail modal via getComparisonProperty() below.
        $this->laps = $perMap->map(function (array $row) use ($recordsByMap): array {
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
        $this->performanceKeys = array_keys($this->laps);

        // All of this player's real laps (every attempt, not just per-map bests) — drives the
        // Stats Card and Fav Servers, both of which need full lap volume, not just PB rows.
        $allLaps = $player->lapTimes()->whereHas('server')->with('server')->get();

        // Recent Laps — the player's actual last 10 attempts, chronological (not the per-map-PB
        // ordering "Performance by Map" uses). Right now, historical lap_times rows are a
        // personal-best-progression log (see docs/database.md), so this will often just be the
        // same rows as Performance by Map in a different order — but the rebuilt webhook logs
        // every attempt going forward (see docs/decisions.md), so this needs to genuinely handle
        // laps that aren't a per-map best too. Those get appended to $laps under a new key
        // (mapRank/points don't apply to a non-PB attempt) so the shared Lap Detail modal can
        // still address them via openLap() regardless of which table opened them.
        $recentLaps = LapTime::where('player_id', $player->id)
            ->whereHas('server')
            ->with(['map', 'server'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $lapIdToKey = collect($this->laps)->mapWithKeys(fn (array $lap, int $key) => [$lap['lapId'] => $key]);

        $this->recentLapKeys = $recentLaps
            ->map(function (LapTime $lap) use ($lapIdToKey, $recordsByMap): int {
                if ($lapIdToKey->has($lap->id)) {
                    return $lapIdToKey[$lap->id];
                }

                $record = $recordsByMap[$lap->map_id] ?? null;
                $key = count($this->laps);

                $this->laps[$key] = [
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

        $this->statsCard = [
            'numRecords' => $ranking['firstPlaces'] ?? 0,
            'top3Finishes' => $ranking['top3'] ?? 0,
            'mapsCompleted' => $ranking['mapsPlayed'] ?? 0,
            'serversPlayed' => $allLaps->pluck('server_id')->unique()->count(),
            'totalValidLaps' => $allLaps->count(),
            'firstSeen' => $allLaps->min('created_at')?->format('d M Y') ?? '—',
            'lastActive' => $allLaps->max('created_at')?->diffForHumans() ?? '—',
        ];

        // Best Performance — curated, not a raw top-3-fastest-laps list (raw times aren't
        // comparable across maps of different lengths, see docs/decisions.md). No dedicated
        // curation algorithm exists yet, so this derives a couple of honest, real-number
        // sentences from the same ranking data above rather than free-floating mock text.
        $recordMaps = $perMap->where('rank', 1)->pluck('map');
        $this->achievements = array_filter([
            $recordMaps->isNotEmpty()
                ? 'Holds the course record on '.$recordMaps->join(', ', ' and ').'.'
                : null,
            $perMap->isNotEmpty()
                ? "Top 3 finish on {$this->statsCard['top3Finishes']} of {$this->statsCard['mapsCompleted']} maps raced."
                : null,
        ]) ?: ['No standout finishes yet — keep racing to climb the leaderboard.'];

        // Fav[orite] Servers — sorted by lap count descending (assumption, see
        // docs/player-single.md). bestRank is this player's best Map Rank among the maps whose
        // PB was set on that server; null (rendered as "—") if none of their per-map bests
        // happen to live on that server.
        $bestRankByServer = $perMap->groupBy('serverId')->map(fn ($group) => $group->min('rank'));

        $this->favServers = $allLaps->groupBy('server_id')
            ->map(fn ($group, $serverId) => [
                'serverId' => (int) $serverId,
                'server' => $group->first()->server->name,
                'laps' => $group->count(),
                'bestRank' => $bestRankByServer[$serverId] ?? null,
            ])
            ->sortByDesc('laps')
            ->values()
            ->all();
    }

    /**
     * Real per-checkpoint split comparison against the map's course-record lap — same pattern
     * as ServerShow, via the shared HasRecordVsRunnerUpReference trait.
     */
    public function getComparisonProperty(): array
    {
        $lap = $this->laps[$this->selectedPlayerIndex] ?? $this->laps[0] ?? null;
        $reference = $this->resolveComparisonReference($lap);

        if (! $lap || ! $reference) {
            return [];
        }

        return LapTimeSplit::compare($lap['lapId'], $reference['lapId']);
    }

    public function getComparisonReferenceProperty(): ?array
    {
        $lap = $this->laps[$this->selectedPlayerIndex] ?? $this->laps[0] ?? null;

        return $this->resolveComparisonReference($lap);
    }

    public function render()
    {
        return view('livewire.players.player-show');
    }
}
