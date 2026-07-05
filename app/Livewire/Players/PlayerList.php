<?php

namespace App\Livewire\Players;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Players', 'active' => 'players'])]
class PlayerList extends Component
{
    public array $stats = [];

    public array $podium = [];

    public array $rest = [];

    public function mount(): void
    {
        // Mock data — redesigned per docs/players-list.md into a Global Leaderboard, replacing
        // the old name/laps/best-lap table. Global Score itself is not real yet (see
        // docs/global-ranking.md) — Phase 2. Same player roster used across the Map Leaderboard
        // mock data for continuity.
        $rows = [
            [1, 'VORTEX', '[SR7]', 812, 6, 9, '12m ago', 'up', 0],
            [2, 'NeonHalo', '[NHC]', 764, 4, 9, '2h ago', 'up', 1],
            [3, 'GravLift', '[ODST]', 701, 3, 8, '38m ago', 'down', 1],
            [4, 'RedTeamRush', '[RED]', 588, 2, 7, '5h ago', 'flat', 0],
            [5, 'SabreWing', '[SAB]', 542, 1, 7, '1d ago', 'up', 2],
            [6, 'ODST_Mako', '[ODST]', 501, 1, 6, '9h ago', 'down', 1],
            [7, 'PelicanDown', '[PEL]', 467, 0, 6, '3d ago', 'flat', 0],
            [8, 'CovyCrusher', '[COV]', 430, 0, 5, '4h ago', 'up', 3],
            [9, 'SpartanII_04', '[S2]', 398, 0, 5, '6d ago', 'down', 2],
            [10, 'WartHogWild', '[WHW]', 356, 0, 4, '2d ago', 'flat', 0],
        ];

        $players = array_map(fn ($r) => [
            'id' => $r[0],
            'rank' => $r[0],
            'name' => $r[1],
            'tag' => $r[2],
            'score' => $r[3],
            'records' => $r[4],
            'maps' => $r[5],
            'active' => $r[6],
            'trendDirection' => $r[7],
            'trendDelta' => $r[8],
        ], $rows);

        // Top 3 podium — shaped for the shared podium partial (resources/views/livewire/partials/podium.blade.php).
        $this->podium = array_map(fn (array $p): array => [
            'title' => $p['name'],
            'subtitle' => $p['tag'],
            'value' => $p['score'],
            'meta' => "{$p['records']} RECORDS · {$p['maps']} MAPS",
            'badge' => $p['rank'] === 1 ? '#1 GLOBAL' : null,
            'href' => route('players.show', ['playerId' => $p['id']]),
        ], array_slice($players, 0, 3));

        // Table below only lists rank 4+ — top 3 are already shown in the podium above.
        $this->rest = array_slice($players, 3);

        // Info card — four stats, meant to make the page feel alive/current. "Total records set"
        // uses the historical-record-breaking-events reading per the doc's leaning (not formally
        // decided project-wide — see docs/roadmap.md), deliberately different from the table's
        // "Records" column below (which is current-state, per-player). Mock only either way.
        $this->stats = [
            'totalPlayers' => 187,
            'active30d' => 96,
            'recordsSet' => 14,
            'avgMapsPerPlayer' => round(collect($players)->avg('maps'), 1),
        ];
    }

    public function render()
    {
        return view('livewire.players.player-list');
    }
}
