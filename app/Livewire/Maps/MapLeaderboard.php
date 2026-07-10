<?php

namespace App\Livewire\Maps;

use App\Livewire\Concerns\HasLapDetailModal;
use App\Livewire\Concerns\HasRankedLeaderboardPagination;
use App\Models\LapTime;
use App\Models\LapTimeSplit;
use App\Models\Map;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Global leaderboard for a map — best laps across all active servers.
 * For the server-scoped (nested) leaderboard, see App\Livewire\Servers\ServerMapLeaderboard.
 */
#[Layout('components.layout', ['title' => 'Map Leaderboard', 'active' => 'maps'])]
class MapLeaderboard extends Component
{
    use HasLapDetailModal, HasRankedLeaderboardPagination;

    public string $mapParam;

    public string $map;

    public int $totalLaps = 0;

    /** @var list<array<string, mixed>> */
    public array $players = [];

    /** @var list<array<string, mixed>> */
    public array $splits = [];

    /** The global #1 lap id, used for the podium sparkline and comparison reference. */
    public ?int $topLapId = null;

    public function mount(string $mapId): void
    {
        $map = Map::findOrFail($mapId);

        $this->mapParam = $mapId;
        $this->map = $map->label;

        $this->loadLeaderboard($map);
    }

    /**
     * Live update (roadmap item 16, retargeted 2026-07-08). Originally listened only on the
     * map-scoped `maps.{mapId}`/`leaderboard.updated` channel (a genuine PB/record on any server
     * for this map) — but that left `$totalLaps` (the "SHOWING X / Y LAPS" footer) stale after
     * every *non*-PB lap, since a non-improving attempt never fires `LeaderboardUpdated` at all.
     * `LapSubmitted` fires on every attempt, improvement or not, and is a strict superset of
     * when `LeaderboardUpdated` fires (see `ProcessNewLap`), so listening on the site-wide
     * `activity` channel instead covers both cases with one listener, matching the pattern
     * already used by `ServerList`/`Home`/etc. Slightly more chatty (re-fetches on any lap
     * anywhere, not just this map), but consistent with the rest of the app's live-update model
     * and simpler than two separate listeners doing the same reload.
     */
    #[On('echo:activity,.lap.submitted')]
    public function onLapSubmitted(): void
    {
        $this->loadLeaderboard(Map::findOrFail($this->mapParam));
    }

    private function loadLeaderboard(Map $map): void
    {
        // Global ranked leaderboard: one best lap per player across every active server. Laps
        // belonging to soft-deleted servers are intentionally treated as nonexistent. Time ties go
        // to the earliest lap; id is the final deterministic tie-break for historical rows that
        // share day-only timestamps. Sorting before unique() also selects the server where that
        // player's actual global PB was set.
        $laps = LapTime::where('map_id', $map->id)
            ->whereHas('server')
            ->with(['player', 'server'])
            ->orderBy('time')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $this->totalLaps = $laps->count();

        $bestPerPlayer = $laps->unique('player_id')->values();
        $topTime = $bestPerPlayer->first()?->time;
        $secondTime = $bestPerPlayer->get(1)?->time;
        $this->topLapId = $bestPerPlayer->first()?->id;

        $this->players = array_values($bestPerPlayer
            ->map(function (LapTime $lap, int $index) use ($topTime, $secondTime): array {
                $gap = $index === 0
                    ? ($secondTime !== null ? '+'.number_format((float) $secondTime - (float) $topTime, 3) : '—')
                    : '+'.number_format((float) $lap->time - (float) $topTime, 3);

                return [
                    'lapId' => $lap->id,
                    'playerId' => $lap->player_id,
                    'serverId' => $lap->server_id,
                    'rank' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                    'name' => $lap->player->name,
                    'subtitle' => $lap->server->name,
                    'time' => $lap->formattedTime(),
                    'gap' => $gap,
                    'date' => $lap->created_at?->diffForHumans() ?? '—',
                    'dateExact' => $lap->created_at ? $lap->created_at->format('d M Y, H:i').' '.$lap->created_at->format('T') : '—',
                ];
            })
            ->all());

        $this->splits = $this->topLapId
            ? array_values(LapTimeSplit::where('lap_time_id', $this->topLapId)
                ->orderBy('checkpoint_id')
                ->get()
                ->map(fn (LapTimeSplit $split, int $index): array => [
                    'label' => 'CP '.($index + 1),
                    'time' => number_format($split->duration, 3),
                ])
                ->all())
            : [];
    }

    /**
     * Real checkpoint comparison; #1 compares against #2 rather than against itself.
     *
     * @return array<int, array{label: string, myTime: string, refTime: ?string, delta: ?string, deltaValue: ?float, running: ?string, faster: ?bool, absDelta: ?float, colorClass: string, barW: ?int, hasReference: bool, usingReferenceSplits: bool}>
     */
    public function getComparisonProperty(): array
    {
        $player = $this->players[$this->selectedPlayerIndex] ?? $this->players[0] ?? null;

        if (! $player) {
            return [];
        }

        $reference = $this->resolveComparisonReference($player);

        // No runner-up/record lap exists yet (e.g. this is the very first lap ever recorded for
        // a freshly-forked race_type/checkpoint-count map variant) — still show this lap's own
        // splits rather than collapsing "no reference lap" into the same "no split data" message
        // as "this lap genuinely has no splits."
        return $reference
            ? LapTimeSplit::compare($player['lapId'], $reference['lapId'])
            : LapTimeSplit::solo($player['lapId']);
    }

    /** @return array<string, mixed>|null */
    public function getComparisonReferenceProperty(): ?array
    {
        $player = $this->players[$this->selectedPlayerIndex] ?? $this->players[0] ?? null;

        return $this->resolveComparisonReference($player);
    }

    /**
     * @param  array<string, mixed>|null  $player
     * @return array<string, mixed>|null
     */
    private function resolveComparisonReference(?array $player): ?array
    {
        if (! $player) {
            return null;
        }

        if ($player['lapId'] === $this->topLapId) {
            $reference = $this->players[1] ?? null;

            return $reference ? [...$reference, 'label' => 'RUNNER-UP'] : null;
        }

        $reference = $this->players[0] ?? null;

        return $reference ? [...$reference, 'label' => 'RECORD'] : null;
    }

    public function render(): View
    {
        return view('livewire.maps.map-leaderboard', [
            'rankedPlayers' => $this->rankedPlayers(),
        ])->layoutData([
            'title' => "{$this->map} Leaderboard",
            'description' => "The global leaderboard for {$this->map} — best lap times across every active Halo Race Leaderboard server.",
        ]);
    }
}
