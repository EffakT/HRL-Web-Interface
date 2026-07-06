<?php

namespace App\Livewire\Servers;

use App\Models\LapTime;
use App\Models\MostActiveServer;
use App\Models\Server;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Servers', 'active' => 'servers'])]
class ServerList extends Component
{
    /**
     * How recent a live query (roadmap item 19, `App\Console\Commands\RefreshLiveServerInfo`,
     * scheduled every minute) needs to be before its stored result is trusted over the
     * lap-history-derived proxy below. A generous margin over the 1-minute schedule so a missed
     * run or two doesn't immediately flip a server back to "unknown."
     */
    private const LIVE_DATA_FRESHNESS_MINUTES = 5;

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
        // - "online" and "now playing" prefer a genuinely live signal (roadmap item 19) — a
        //   scheduled job UDP-queries each server and stores the result; a recent successful
        //   query wins. Falls back to the pre-existing recency proxies ("has a lap in the last
        //   24h" / most recent lap's map) when no live data exists yet or it's gone stale
        //   (server unreachable for a while, or the scheduler hasn't run) — see buildRow(). The
        //   table's "online" means "the server process is reachable"; the featured card is
        //   held to a stricter bar ("you could join a race right now") requiring a live player
        //   count > 0 when fresh live data exists — see the featured-card override below.
        // - No player-capacity (`players_now`/`players_max`) columns — the "load" bar is now
        //   relative to the busiest server in this list, not a literal capacity percentage.
        // - "Most active" uses the real Activity Score algorithm (docs/most-active-server.md,
        //   roadmap item 12) — Unique Players × 10 + Valid Laps × 1 + Maps Played × 20 over a
        //   90-day window, plus a recency bonus, computed fresh via App\Models\MostActiveServer.
        $servers = Server::with('currentMap')->get();

        $rows = $servers->map(fn (Server $server) => $this->buildRow($server))->values();

        $maxPlayers = max($rows->pluck('playersRaw')->max(), 1);

        $this->servers = $rows->map(fn (array $row) => [
            ...$row,
            'playersPct' => (int) round($row['playersRaw'] / $maxPlayers * 100),
        ])->all();

        $topServerId = MostActiveServer::scores()[0]['serverId'] ?? null;

        $this->featured = collect($this->servers)->firstWhere('id', $topServerId)
            ?? $this->servers[0] ?? [];

        // The featured card is held to a stricter "online" bar than the table rows: "you could
        // join a race right now," not merely "the server process is reachable." Only applies
        // when we actually have a fresh live player count to check — otherwise the card keeps
        // the same recency-proxy value as everyone else.
        if (($this->featured['livePlayerCount'] ?? null) !== null) {
            $this->featured['online'] = $this->featured['livePlayerCount'] > 0;
        }

        $this->onlineCount = collect($this->servers)->where('online', true)->count();
        $this->totalPlayers = LapTime::distinct('player_id')->count('player_id');
        $this->lapsToday = LapTime::whereDate('created_at', today())->count();
    }

    private function buildRow(Server $server): array
    {
        $lastLap = $server->lapTimes()->with('map')->orderByDesc('created_at')->first();
        $laps = $server->lapTimes()->count();
        $players = $server->lapTimes()->distinct('player_id')->count('player_id');

        $best = $lastLap
            ? $server->lapTimes()->where('map_id', $lastLap->map_id)->min('time')
            : null;

        $queriedRecently = $server->queried_at !== null
            && $server->queried_at->gte(now()->subMinutes(self::LIVE_DATA_FRESHNESS_MINUTES));

        // A recent successful query wins outright (it's a direct answer). A recent *failed*
        // query is still more trustworthy than the recency proxy for "online" specifically —
        // it just confirmed the server is unreachable right now — but falls back to the proxy
        // for "map", since a failed query has no live map to report.
        $online = $queriedRecently
            ? (bool) $server->query_successful
            : ($lastLap !== null && $lastLap->created_at !== null && $lastLap->created_at->gte(now()->subDay()));

        $map = $queriedRecently && $server->query_successful && $server->currentMap !== null
            ? $server->currentMap->label
            : ($lastLap !== null && $lastLap->map !== null ? $lastLap->map->label : '—');

        // Only meaningful when a fresh, successful query actually happened — null otherwise, so
        // the featured-card override in mount() knows not to second-guess the recency proxy.
        $livePlayerCount = $queriedRecently && $server->query_successful
            ? ($server->live_player_count ?? 0)
            : null;

        return [
            'id' => $server->id,
            'name' => $server->name,
            'online' => $online,
            'map' => $map,
            'livePlayerCount' => $livePlayerCount,
            'players' => number_format($players),
            'playersRaw' => $players,
            'laps' => number_format($laps),
            'best' => $best !== null ? LapTime::formatSeconds($best) : '—',
        ];
    }

    public function render()
    {
        return view('livewire.servers.server-list');
    }
}
