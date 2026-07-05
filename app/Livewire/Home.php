<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Home', 'active' => 'home'])]
class Home extends Component
{
    public array $highlights = [];

    public array $quickStats = [];

    public function mount(): void
    {
        // Mock data — six candidate highlight blocks, keyed by type. An empty array means
        // "nothing to show" (e.g. no new records this week). One block below is deliberately
        // left empty so the fixed-priority fallback selection actually gets exercised, not just
        // the visual design of whichever block happens to be first. See docs/homepage.md.
        $candidates = [
            'records' => [
                ['map' => 'Timberland Loop', 'time' => '1:41.220', 'player' => 'GravLift', 'server' => 'Timberland Rally Cross', 'ago' => '38m ago'],
                ['map' => 'Sidewinder Circuit', 'time' => '1:03.774', 'player' => 'VORTEX', 'server' => 'Sidewinder Speedway 24/7', 'ago' => '2h ago'],
                ['map' => 'Bloodgulch Circuit', 'time' => '1:24.902', 'player' => 'NeonHalo', 'server' => 'Bloodgulch Grand Prix', 'ago' => '5h ago'],
            ],
            'most-active-server' => [
                ['rank' => 1, 'name' => 'Coldsnap Circuit [Classic]', 'players30d' => 42, 'players90d' => 118, 'lastActive' => '2m ago'],
                ['rank' => 2, 'name' => 'Bloodgulch Grand Prix', 'players30d' => 35, 'players90d' => 96, 'lastActive' => '11m ago'],
                ['rank' => 3, 'name' => 'Sidewinder Speedway 24/7', 'players30d' => 29, 'players90d' => 84, 'lastActive' => '4m ago'],
            ],
            // Deliberately empty this round — exercises the fixed-priority fallback down to
            // "New Maps / Servers" below, per docs/homepage.md's selection rule.
            'fastest-improvements' => [],
            'new-content' => [
                ['type' => 'map', 'name' => 'Danger Canyon Drift', 'ago' => '3d ago'],
                ['type' => 'server', 'name' => 'Ratrace Rumble', 'ago' => '6d ago'],
            ],
            'achievements' => [
                ['player' => 'SabreWing', 'note' => "first course record — Hang 'Em High Sprints"],
            ],
            'live-stats' => [
                'totalLaps' => 128_406,
                'activePlayers30d' => 61,
                'activePlayers90d' => 143,
                'activeServers30d' => 5,
                'activeServers90d' => 6,
                'mapsToday' => 4,
                'mapsThisWeek' => 9,
            ],
        ];

        $priority = ['records', 'most-active-server', 'fastest-improvements', 'new-content', 'achievements', 'live-stats'];

        $this->highlights = collect($priority)
            ->map(fn (string $type): array => ['type' => $type, 'data' => $candidates[$type]])
            ->filter(fn (array $block): bool => ! empty($block['data']))
            ->take(3)
            ->values()
            ->all();

        // Quick Stats — always shown, simple all-time counts, independent of the highlights above.
        $this->quickStats = [
            'players' => 187,
            'servers' => 6,
            'laps' => 128_406,
        ];
    }

    public function render()
    {
        return view('livewire.home');
    }
}
