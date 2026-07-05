<?php

namespace App\Livewire\Servers;

use App\Models\LapTime;
use App\Models\Server;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Servers', 'active' => 'servers'])]
class ServerList extends Component
{
    public array $featured;

    public array $servers;

    public int $onlineCount = 0;

    public int $totalPlayers = 0;

    public int $lapsToday = 0;

    public function mount(): void
    {
        // Real data. Several fields from the original mock have no real-schema equivalent and
        // are either dropped or replaced with an honest derived proxy — see docs/database.md
        // and docs/decisions.md:
        // - No `region` column on `servers` — dropped entirely, not fabricated.
        // - No live online/heartbeat signal — "online" here means "has a lap in the last 24h",
        //   a recency proxy, not a real-time status check against the game server.
        // - No `current_map_id` column — "now playing" is derived from each server's most
        //   recent lap's map, per the approach already documented in docs/database.md.
        // - No player-capacity (`players_now`/`players_max`) columns — the "load" bar is now
        //   relative to the busiest server in this list, not a literal capacity percentage.
        // - "Most active" uses a simple real proxy (laps in the last 30 days, tie-broken by most
        //   recent lap) — NOT the full Activity Score algorithm from most-active-server.md,
        //   which needs a scheduled recalculation job and is still roadmap item 12.
        $servers = Server::all();

        $rows = $servers->map(fn (Server $server) => $this->buildRow($server))->values();

        $maxPlayers = max($rows->pluck('playersRaw')->max(), 1);

        $this->servers = $rows->map(fn (array $row) => [
            ...$row,
            'playersPct' => (int) round($row['playersRaw'] / $maxPlayers * 100),
        ])->all();

        $this->featured = collect($this->servers)
            ->sortByDesc(fn (array $row) => [$row['laps30d'], $row['lastLapAt'] !== null ? $row['lastLapAt']->timestamp : 0])
            ->first() ?? $this->servers[0] ?? [];

        $this->onlineCount = collect($this->servers)->where('online', true)->count();
        $this->totalPlayers = LapTime::distinct('player_id')->count('player_id');
        $this->lapsToday = LapTime::whereDate('created_at', today())->count();
    }

    private function buildRow(Server $server): array
    {
        $lastLap = $server->lapTimes()->with('map')->orderByDesc('created_at')->first();
        $laps = $server->lapTimes()->count();
        $players = $server->lapTimes()->distinct('player_id')->count('player_id');
        $laps30d = $server->lapTimes()->where('created_at', '>=', now()->subDays(30))->count();

        $best = $lastLap
            ? $server->lapTimes()->where('map_id', $lastLap->map_id)->min('time')
            : null;

        return [
            'id' => $server->id,
            'name' => $server->name,
            'online' => $lastLap !== null && $lastLap->created_at !== null && $lastLap->created_at->gte(now()->subDay()),
            'map' => $lastLap !== null && $lastLap->map !== null ? $lastLap->map->label : '—',
            'players' => number_format($players),
            'playersRaw' => $players,
            'laps' => number_format($laps),
            'laps30d' => $laps30d,
            'best' => $best !== null ? LapTime::formatSeconds($best) : '—',
            'lastLapAt' => $lastLap?->created_at,
        ];
    }

    public function render()
    {
        return view('livewire.servers.server-list');
    }
}
