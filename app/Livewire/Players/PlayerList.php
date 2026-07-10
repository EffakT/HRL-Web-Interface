<?php

namespace App\Livewire\Players;

use App\Livewire\Concerns\HasRankedLeaderboardPagination;
use App\Models\GlobalRanking;
use App\Models\LapTime;
use App\Models\RecordHistory;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Players', 'active' => 'players', 'description' => 'The global player leaderboard for the Halo Race Leaderboard, ranked by score across every tracked map and server.'])]
class PlayerList extends Component
{
    use HasRankedLeaderboardPagination;

    /** @var array<string, int|float> */
    public array $stats = [];

    /** @var list<array<string, mixed>> */
    public array $podium = [];

    /**
     * Every ranked player, keyed 0-indexed by Global Score rank — top 3 render as the podium, rest are paginated via HasRankedLeaderboardPagination.
     *
     * @var list<array<string, mixed>>
     */
    public array $players = [];

    public function mount(): void
    {
        $this->loadPlayers();
    }

    /**
     * Live update (roadmap item 16 follow-up) — the Global Leaderboard changes on any lap by any
     * player, so this listens on the site-wide `activity` channel (matching ServerList/Home).
     */
    #[On('echo:activity,.lap.submitted')]
    public function loadPlayers(): void
    {
        $rankings = collect(GlobalRanking::scores());

        // Real, per-player "last active" (most recent lap on any active server) and total lap
        // count (every attempt, any server) — each in one query rather than N+1 per player.
        $lastActive = LapTime::query()
            ->whereHas('server')
            ->selectRaw('player_id, MAX(created_at) as last_active')
            ->groupBy('player_id')
            ->pluck('last_active', 'player_id');

        $lapCounts = LapTime::query()
            ->whereHas('server')
            ->selectRaw('player_id, COUNT(*) as laps')
            ->groupBy('player_id')
            ->pluck('laps', 'player_id');

        // No clan/tag field exists in the real schema (see docs/decisions.md — dropped
        // everywhere else for the same reason). Trend indicator dropped too: its mechanism
        // (periodic rank/score snapshots vs. a recent-activity proxy) is still an open roadmap
        // question, and there's no real signal to show honestly in the meantime.
        $this->players = array_values($rankings
            ->map(fn (array $p): array => [
                'id' => $p['playerId'],
                'rank' => $p['rank'],
                'name' => $p['name'],
                'score' => $p['score'],
                'records' => $p['firstPlaces'],
                'maps' => $p['mapsPlayed'],
                'laps' => (int) ($lapCounts[$p['playerId']] ?? 0),
                'active' => isset($lastActive[$p['playerId']])
                    ? Carbon::parse($lastActive[$p['playerId']])->diffForHumans()
                    : '—',
            ])
            ->all());

        // Top 3 podium — shaped for the shared podium partial (resources/views/livewire/partials/podium.blade.php).
        // Same "# RECORDS · # MAPS · # LAPS" stat line as Server Single's Top Players podium, per
        // explicit request to keep these consistent across every ranked-player display.
        $this->podium = array_map(fn (array $p): array => [
            'title' => $p['name'],
            'subtitle' => null,
            'value' => $p['score'],
            'meta' => "{$p['records']} RECORDS · {$p['maps']} MAPS · {$p['laps']} LAPS",
            'badge' => $p['rank'] === 1 ? '#1 GLOBAL' : null,
            'href' => route('players.show', ['playerId' => $p['id']]),
        ], array_slice($this->players, 0, 3));

        $this->stats = [
            'totalPlayers' => count($this->players),
            'active30d' => $lastActive->filter(fn ($date) => $date >= now()->subDays(30)->toDateTimeString())->count(),
            // Historical reading (2026-07-06, via App\Models\RecordHistory) — a real,
            // ever-growing count of record-breaking events over time, not "records currently
            // held" (that's the per-player "Records" table column below, which deliberately
            // keeps the current-state reading — see docs/roadmap.md's "Number of records set"
            // open item and docs/players-list.md, which explicitly allows these two to differ).
            'recordsSet' => count(RecordHistory::events()),
            'avgMapsPerPlayer' => round($rankings->avg('mapsPlayed') ?? 0, 1),
        ];
    }

    public function render(): View
    {
        return view('livewire.players.player-list', [
            'rankedPlayers' => $this->rankedPlayers(),
        ]);
    }
}
