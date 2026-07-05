<?php

namespace App\Livewire\Maps;

use App\Livewire\Concerns\HasLapDetailModal;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Global leaderboard for a map — best laps across ALL servers.
 * For the server-scoped (nested) leaderboard, see App\Livewire\Servers\ServerMapLeaderboard.
 */
#[Layout('components.layout', ['title' => 'Map Leaderboard', 'active' => 'maps'])]
class MapLeaderboard extends Component
{
    use HasLapDetailModal;

    public string $mapParam;

    public string $map = 'Coldsnap Rally';

    public array $players = [];

    public array $splits = [];

    public function mount(string $mapId): void
    {
        // Mock data — mirrors redesign-files/Map Leaderboard.dc.html + Lap Detail.dc.html sample data.
        // TODO: replace with real lap_times/lap_time_splits queries (aggregated across all servers) once backend integration is wired up.
        $this->mapParam = $mapId;

        $rows = [
            [1, 'VORTEX', '[SR7]', '1:12.408', '—', '28 JUN 2026', 22],
            [2, 'NeonHalo', '[NHC]', '1:12.951', '+0.543', '26 JUN 2026', 31],
            [3, 'GravLift', '[ODST]', '1:13.220', '+0.812', '24 JUN 2026', 40],
            [4, 'RedTeamRush', '[RED]', '1:14.006', '+1.598', '22 JUN 2026', 27],
            [5, 'SabreWing', '[SAB]', '1:14.552', '+2.144', '21 JUN 2026', 55],
            [6, 'ODST_Mako', '[ODST]', '1:15.113', '+2.705', '19 JUN 2026', 48],
            [7, 'PelicanDown', '[PEL]', '1:15.889', '+3.481', '17 JUN 2026', 33],
            [8, 'CovyCrusher', '[COV]', '1:16.402', '+3.994', '15 JUN 2026', 61],
            [9, 'SpartanII_04', '[S2]', '1:17.230', '+4.822', '12 JUN 2026', 44],
            [10, 'WartHogWild', '[WHW]', '1:18.115', '+5.707', '09 JUN 2026', 29],
        ];

        $this->players = array_map(fn ($r) => [
            'rank' => str_pad((string) $r[0], 2, '0', STR_PAD_LEFT),
            'name' => $r[1],
            'tag' => $r[2],
            'time' => $r[3],
            'gap' => $r[4],
            'date' => $r[5],
            'ping' => $r[6],
        ], $rows);

        // 14 splits — deliberately more than 3 to exercise the podium sparkline + scrollable modal on high-split-count maps.
        $this->splits = [
            ['label' => 'CP 1', 'time' => '5.200'],
            ['label' => 'CP 2', 'time' => '5.350'],
            ['label' => 'CP 3', 'time' => '4.950'],
            ['label' => 'CP 4', 'time' => '5.600'],
            ['label' => 'CP 5', 'time' => '5.100'],
            ['label' => 'CP 6', 'time' => '5.450'],
            ['label' => 'CP 7', 'time' => '4.850'],
            ['label' => 'CP 8', 'time' => '5.300'],
            ['label' => 'CP 9', 'time' => '5.050'],
            ['label' => 'CP 10', 'time' => '5.500'],
            ['label' => 'CP 11', 'time' => '4.900'],
            ['label' => 'CP 12', 'time' => '5.400'],
            ['label' => 'CP 13', 'time' => '5.150'],
            ['label' => 'CP 14', 'time' => '5.283'],
        ];
    }

    public function render()
    {
        return view('livewire.maps.map-leaderboard');
    }
}
