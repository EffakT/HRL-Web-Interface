<?php

namespace App\Livewire\Maps;

use App\Livewire\Concerns\HasLapDetailModal;
use App\Models\LapTime;
use App\Models\LapTimeSplit;
use App\Models\Map;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Global leaderboard for a map — best laps across all active servers.
 * For the server-scoped (nested) leaderboard, see App\Livewire\Servers\ServerMapLeaderboard.
 */
#[Layout('components.layout', ['title' => 'Map Leaderboard', 'active' => 'maps'])]
class MapLeaderboard extends Component
{
    use HasLapDetailModal;

    public string $mapParam;

    public string $map;

    public int $totalLaps = 0;

    public array $players = [];

    public array $splits = [];

    /** The global #1 lap id, used for the podium sparkline and comparison reference. */
    public ?int $topLapId = null;

    public function mount(string $mapId): void
    {
        $map = Map::findOrFail($mapId);

        $this->mapParam = $mapId;
        $this->map = $map->label;

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

        $this->players = $bestPerPlayer
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
                    'date' => $lap->created_at->diffForHumans(),
                    'dateExact' => $lap->created_at->format('d M Y, H:i').' '.$lap->created_at->format('T'),
                ];
            })
            ->all();

        $this->splits = $this->topLapId
            ? LapTimeSplit::where('lap_time_id', $this->topLapId)
                ->orderBy('checkpoint_id')
                ->get()
                ->map(fn (LapTimeSplit $split, int $index): array => [
                    'label' => 'CP '.($index + 1),
                    'time' => number_format($split->duration, 3),
                ])
                ->all()
            : [];
    }

    /** Real checkpoint comparison; #1 compares against #2 rather than against itself. */
    public function getComparisonProperty(): array
    {
        $player = $this->players[$this->selectedPlayerIndex] ?? $this->players[0] ?? null;
        $reference = $this->resolveComparisonReference($player);

        if (! $player || ! $reference) {
            return [];
        }

        return LapTimeSplit::compare($player['lapId'], $reference['lapId']);
    }

    public function getComparisonReferenceProperty(): ?array
    {
        $player = $this->players[$this->selectedPlayerIndex] ?? $this->players[0] ?? null;

        return $this->resolveComparisonReference($player);
    }

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

    public function render()
    {
        return view('livewire.maps.map-leaderboard');
    }
}
