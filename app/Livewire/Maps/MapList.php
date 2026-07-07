<?php

namespace App\Livewire\Maps;

use App\Models\LapTime;
use App\Models\Map;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Maps', 'active' => 'maps'])]
class MapList extends Component
{
    public array $maps = [];

    public function mount(): void
    {
        $this->loadMaps();
    }

    /**
     * Live update (roadmap item 16 follow-up) — per-map lap counts/best times change on any
     * submitted lap, so this listens on the site-wide `activity` channel (matching
     * ServerList/Home) rather than subscribing to every individual map's own channel.
     */
    #[On('echo:activity,.lap.submitted')]
    public function loadMaps(): void
    {
        // Real global data. `maps.name` is the machine slug; `maps.label` is the public label.
        // The inner joins deliberately exclude maps with no laps on active servers because they
        // have no global leaderboard to visit. Soft-deleted servers are treated as nonexistent.
        $maps = Map::query()
            ->join('lap_times', 'maps.id', '=', 'lap_times.map_id')
            ->join('servers', 'servers.id', '=', 'lap_times.server_id')
            ->whereNull('servers.deleted_at')
            ->select(['maps.id', 'maps.label'])
            ->selectRaw('COUNT(lap_times.id) as laps')
            ->selectRaw('MIN(lap_times.time) as best')
            ->groupBy('maps.id', 'maps.label')
            ->orderBy('maps.label')
            ->toBase()
            ->get();

        $this->maps = $maps->map(fn (object $map) => [
            'id' => $map->id,
            'name' => $map->label,
            'laps' => number_format((int) $map->laps),
            'best' => LapTime::formatSeconds($map->best),
        ])->all();
    }

    public function render()
    {
        return view('livewire.maps.map-list');
    }
}
