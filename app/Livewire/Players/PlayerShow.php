<?php

namespace App\Livewire\Players;

use App\Livewire\Concerns\HasLapDetailModal;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Player', 'active' => 'players'])]
class PlayerShow extends Component
{
    use HasLapDetailModal;

    public string $playerId;

    public string $playerName = 'VORTEX';

    public string $playerTag = '[SR7]';

    public array $playerInfo = [];

    public array $statsCard = [];

    public array $achievements = [];

    public array $laps = [];

    public array $favServers = [];

    public function mount(string $playerId): void
    {
        // Mock data — a player's laps across maps/servers.
        // TODO: replace with a real lap_times query scoped to this player once backend integration is wired up.
        $this->playerId = $playerId;

        $rows = [
            [1, 'Coldsnap Rally', 'Coldsnap Circuit [Classic]', '1:12.408', '28 JUN 2026', 22, 'VORTEX', '[SR7]', 1, 100],
            [2, 'Bloodgulch Circuit', 'Bloodgulch Grand Prix', '1:24.902', '26 JUN 2026', 31, 'NeonHalo', '[NHC]', 2, 95],
            [3, 'Timberland Loop', 'Timberland Rally Cross', '1:41.220', '24 JUN 2026', 40, 'GravLift', '[ODST]', 4, 86],
            [4, 'HEH Sprint', 'Hang \'Em High Sprints', '0:58.311', '22 JUN 2026', 27, 'RedTeamRush', '[RED]', 1, 100],
            [5, 'Danger Canyon', 'Danger Canyon Drift', '1:58.744', '21 JUN 2026', 55, 'SabreWing', '[SAB]', 7, 76],
            [6, 'Sidewinder Circuit', 'Sidewinder Speedway 24/7', '1:03.774', '19 JUN 2026', 22, 'ODST_Mako', '[ODST]', 3, 90],
        ];

        // "Performance by Map" (Map/PB/Map Rank/Points/Server) and the existing Lap Detail modal
        // share this same array/index — every row here is this player's single best lap on that
        // map, which is exactly what the modal's split comparison expects. "Recent Laps" below
        // reuses this same array too rather than inventing a second selection index; with only
        // one lap per map in this mock dataset, "recent" and "per-map PB" happen to coincide —
        // at real scale a player would have many laps per map, so the two views would diverge.
        $this->laps = array_map(fn ($r) => [
            'mapId' => $r[0],
            'map' => $r[1],
            'server' => $r[2],
            'time' => $r[3],
            'date' => $r[4],
            'ping' => $r[5],
            'recordHolder' => $r[6],
            'recordTag' => $r[7],
            // Mock: this player's time happens to match the map record in this data set — TODO: derive for real once backend integration is wired up.
            'recordTime' => $r[3],
            'mapRank' => $r[8],
            'points' => $r[9],
        ], $rows);

        // Player Info (header) — Global Rank/Score depend on global-ranking.md, not real yet.
        $this->playerInfo = [
            'globalRank' => 1,
            'globalScore' => 812,
        ];

        // Stats Card. "Num Records" and "Total valid laps" use the plain/current-state readings
        // per docs/player-single.md (deliberately distinct from other pages' historical-reading
        // "records" stats and from most-active-server.md's dedup'd "Valid Laps" — see glossary).
        $this->statsCard = [
            'numRecords' => 2,
            'top3Finishes' => 5,
            'mapsCompleted' => count($rows),
            'serversPlayed' => collect($rows)->pluck('2')->unique()->count(),
            'totalValidLaps' => 412,
            'firstSeen' => '14 JAN 2025',
            'lastActive' => '12m ago',
        ];

        // Best Performance — curated achievements, not a raw top-3-fastest-laps list. Raw times
        // aren't comparable across maps of different lengths (see docs/decisions.md — the same
        // reasoning that got Server Single's "Top 3 Fastest Laps" removed applies here too).
        $this->achievements = [
            'Holds the course record on HEH Sprint and Coldsnap Rally',
            'Top 3 finish on 5 of 6 maps raced',
            "Fastest player on the server's most active map, Coldsnap Rally",
        ];

        // Fav[orite] Servers — sorted by lap count descending (assumption, see docs/player-single.md).
        $this->favServers = [
            ['serverId' => 1, 'server' => 'Coldsnap Circuit [Classic]', 'laps' => 168, 'bestRank' => 1],
            ['serverId' => 6, 'server' => 'Sidewinder Speedway 24/7', 'laps' => 94, 'bestRank' => 2],
            ['serverId' => 2, 'server' => 'Bloodgulch Grand Prix', 'laps' => 61, 'bestRank' => 1],
        ];
    }

    public function render()
    {
        return view('livewire.players.player-show');
    }
}
