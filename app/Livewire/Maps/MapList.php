<?php

namespace App\Livewire\Maps;

use App\Models\LapTime;
use App\Models\Map;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Maps', 'active' => 'maps'])]
class MapList extends Component
{
    public array $maps = [];

    public function mount(): void
    {
        // Real global data. `maps.name` is the machine slug; `maps.label` is the public label.
        // The inner join deliberately excludes maps with no laps because they have no global
        // leaderboard to visit. Counts and bests span every server.
        $maps = Map::query()
            ->join('lap_times', 'maps.id', '=', 'lap_times.map_id')
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
