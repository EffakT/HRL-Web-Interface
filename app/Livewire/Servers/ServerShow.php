<?php

namespace App\Livewire\Servers;

use App\Livewire\Concerns\HasLapDetailModal;
use App\Livewire\Concerns\HasRankedLeaderboardPagination;
use App\Livewire\Concerns\HasRecordVsRunnerUpReference;
use App\Models\GlobalRanking;
use App\Models\LapTime;
use App\Models\LapTimeSplit;
use App\Models\Server;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Server', 'active' => 'servers'])]
class ServerShow extends Component
{
    use HasLapDetailModal, HasRankedLeaderboardPagination, HasRecordVsRunnerUpReference;

    public int $serverId;

    public string $serverName;

    public string $serverIp;

    public string $serverPort;

    /** @var list<array<string, mixed>> */
    public array $maps = [];

    /** @var array<string, int> */
    public array $stats = [];

    /** @var list<array<string, mixed>> */
    public array $topPlayers = [];

    /**
     * Every player ranked by Server Score on this server — HasRankedLeaderboardPagination slices ranks 4+ from this.
     *
     * @var list<array<string, mixed>>
     */
    public array $players = [];

    /**
     * Current page's Latest Laps rows, indexed the same as rendered — read by the Lap Detail modal via $selectedPlayerIndex.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $latestLaps = [];

    public function mount(string $serverId): void
    {
        $server = Server::findOrFail($serverId);

        $this->serverId = $server->id;
        $this->serverName = $server->name;
        $this->serverIp = $server->ip;
        $this->serverPort = $server->port;

        $this->loadServerData($server);
    }

    /**
     * Live update (roadmap item 16 follow-up) — Maps, Stats, and Top Players all change on any
     * submitted lap on this server, not just a PB, so this listens on the site-wide `activity`
     * channel rather than a map-scoped one (matching ServerList/Home's same choice). Latest Laps
     * and Top Players' pagination don't need their own listener — render() re-runs laps() and
     * rankedPlayers() on every re-render, which this method's execution alone triggers.
     *
     * A parameterless wrapper, not the `#[On(...)]` attribute directly on `loadServerData()`:
     * Livewire's Echo bridge dispatches the broadcast payload as this listener's first argument,
     * which would hit `loadServerData(?Server $server)`'s type hint with an array/object instead
     * of a `Server`.
     */
    #[On('echo:activity,.lap.submitted')]
    public function onLapSubmitted(): void
    {
        $this->loadServerData();
    }

    public function loadServerData(?Server $server = null): void
    {
        $server ??= Server::findOrFail($this->serverId);

        // Per-map lap count + this server's best time, in one query rather than N+1 per map.
        // ->toBase() drops back to a plain query builder — these rows are aggregates (map_id,
        // laps, best), not real LapTime records, so there's no reason to hydrate Eloquent models.
        $serverMapStats = LapTime::where('server_id', $server->id)
            ->selectRaw('map_id, COUNT(*) as laps, MIN(time) as best')
            ->groupBy('map_id')
            ->toBase()
            ->get()
            ->keyBy('map_id');

        $this->maps = array_values($server->maps
            ->map(fn ($map): array => [
                'id' => $map->id,
                'name' => $map->label,
                'laps' => number_format($serverMapStats[$map->id]->laps ?? 0),
                'best' => isset($serverMapStats[$map->id])
                    ? LapTime::formatSeconds($serverMapStats[$map->id]->best)
                    : '—',
            ])
            ->all());

        // Stats badges — split into all-time + 30d/90d activity windows (matching the recency
        // pattern used elsewhere, e.g. most-active-server.md), rather than one flat all-time
        // number. Derived directly from lap_times rather than the players_servers pivot, which
        // has duplicate rows (see docs/database.md) — counting distinct players who actually
        // have a lap here is both more accurate and avoids that pitfall entirely. "Records set"
        // was dropped — that's a player-level stat (see players-list.md/player-single.md), not
        // a server one, per explicit feedback.
        $baseQuery = fn () => LapTime::where('server_id', $server->id);

        $this->stats = [
            'totalLaps' => $baseQuery()->count(),
            'laps30d' => $baseQuery()->where('created_at', '>=', now()->subDays(30))->count(),
            'laps90d' => $baseQuery()->where('created_at', '>=', now()->subDays(90))->count(),
            'totalPlayers' => $baseQuery()->distinct('player_id')->count('player_id'),
            'activePlayers30d' => $baseQuery()->where('created_at', '>=', now()->subDays(30))->distinct('player_id')->count('player_id'),
            'activePlayers90d' => $baseQuery()->where('created_at', '>=', now()->subDays(90))->distinct('player_id')->count('player_id'),
        ];

        // Top Players — "Server Score" (docs/global-ranking.md's "Scoped variant"): the same
        // points formula as the Global Leaderboard, applied to this server's own nested per-map
        // leaderboards only, via GlobalRanking::scores($serverId). Every ranked player is kept
        // (not just the top 3) so the table below the podium can show ranks 4+, same structure
        // as the Global Leaderboard (see HasRankedLeaderboardPagination).
        $serverRanking = GlobalRanking::scores($server->id);

        $playerLapCounts = LapTime::where('server_id', $server->id)
            ->whereIn('player_id', array_column($serverRanking, 'playerId'))
            ->selectRaw('player_id, COUNT(*) as laps')
            ->groupBy('player_id')
            ->toBase()
            ->get()
            ->keyBy('player_id');

        // Same "# Records, # Laps, # Maps" stat set as the Global Leaderboard, per explicit
        // request to keep ranked-player displays consistent — 'records'/'maps' come straight off
        // GlobalRanking's per-scope firstPlaces/mapsPlayed, already scoped to this server.
        $this->players = array_values(collect($serverRanking)
            ->map(fn (array $player): array => [
                'id' => $player['playerId'],
                'rank' => $player['rank'],
                'name' => $player['name'],
                'score' => $player['score'],
                'records' => $player['firstPlaces'],
                'maps' => $player['mapsPlayed'],
                'laps' => (int) ($playerLapCounts[$player['playerId']]->laps ?? 0),
            ])
            ->all());

        $this->topPlayers = array_map(fn (array $player): array => [
            'title' => $player['name'],
            'subtitle' => null,
            'value' => $player['score'],
            'meta' => "{$player['records']} RECORDS · {$player['maps']} MAPS · {$player['laps']} LAPS",
            'badge' => $player['rank'] === 1 ? '#1 SERVER' : null,
            'href' => route('servers.players.show', ['serverId' => $server->id, 'playerId' => $player['id']]),
        ], array_slice($this->players, 0, 3));
    }

    /**
     * Latest Laps — a plain reverse-chronological feed of every lap on this server, not
     * deduplicated per (player, map). A player who ran 5 laps in a row shows up 5 times — that's
     * correct for a feed, not a bug (see docs/decisions.md; an earlier pass deduplicated this,
     * which was wrong for what this section is meant to be).
     *
     * Rows open the shared Lap Detail modal (HasLapDetailModal + lap-vs-record-modal.blade.php,
     * extracted from Player Single's inline copy — this is its second real consumer). The
     * transformed page is also stashed on $latestLaps so the modal can index into the exact
     * same array the page rendered, regardless of pagination.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function laps(): LengthAwarePaginator
    {
        $paginator = LapTime::where('server_id', $this->serverId)
            ->with(['map', 'player'])
            ->orderByDesc('created_at')
            ->paginate(15);

        $mapIds = collect($paginator->items())->pluck('map_id')->unique();

        // Course record (global best, any active server) per map on this page — cheap summary line for
        // the modal's "vs MAP RECORD" comparison. Split-by-split comparison itself stays mock
        // (see lap-vs-record-modal.blade.php) — real per-checkpoint comparison isn't built yet.
        $recordsByMap = LapTime::whereIn('map_id', $mapIds)
            ->whereHas('server')
            ->with('player')
            ->get()
            ->groupBy('map_id')
            ->map(fn ($group) => $group->sortBy('time')->first());

        $paginator->through(fn (LapTime $lap): array => $this->formatLatestLapRow($lap, $recordsByMap[$lap->map_id] ?? null));

        $this->latestLaps = $paginator->items();

        return $paginator;
    }

    /**
     * Extracted out of `laps()` so its return type is declared, not structurally inferred —
     * `LengthAwarePaginator::through()`'s TValue is non-covariant, so `laps()`'s own `@return`
     * generic needs the closure's result to match it invariantly; an inline closure's inferred
     * literal type (e.g. `mapId: int<0, max>`) doesn't unify with the declared shape even though
     * it's a valid subtype, where a named method's own declared return type does.
     *
     * @return array<string, mixed>
     */
    private function formatLatestLapRow(LapTime $lap, ?LapTime $record): array
    {
        return [
            'lapId' => $lap->id,
            'mapId' => $lap->map_id,
            'recordLapId' => $record?->id,
            'player' => $lap->player->name,
            'map' => $lap->map->label,
            'server' => $this->serverName,
            'time' => $lap->formattedTime(),
            // Relative ("3h ago") rather than an absolute date/time — avoids implying a
            // timezone to an international audience. The exact date/time + timezone
            // abbreviation is still available on hover (see server-show.blade.php).
            'date' => $lap->created_at?->diffForHumans() ?? '—',
            'dateExact' => $lap->created_at ? $lap->created_at->format('d M Y, H:i').' '.$lap->created_at->format('T') : '—',
            'recordHolder' => $record?->player->name ?? '—',
            'recordTime' => $record ? LapTime::formatSeconds($record->time) : '—',
        ];
    }

    /**
     * Real per-checkpoint split comparison against the map's course-record lap — overrides
     * HasLapDetailModal's mock version (same hardcoded rows regardless of which lap was
     * selected, which is what the user noticed and flagged: "they all seem the same"). See
     * LapTimeSplit::compare() for the shared comparison math (also used by
     * ServerMapLeaderboard). Only ~4% of real laps have split rows at all, so this is
     * frequently empty — the view shows a "no split data" message rather than fabricating rows.
     *
     * @return array<int, array{label: string, myTime: string, refTime: ?string, delta: ?string, deltaValue: ?float, running: ?string, faster: ?bool, absDelta: ?float, colorClass: string, barW: ?int, hasReference: bool, usingReferenceSplits: bool}>
     */
    public function getComparisonProperty(): array
    {
        $lap = $this->latestLaps[$this->selectedPlayerIndex] ?? $this->latestLaps[0] ?? null;

        if (! $lap) {
            return [];
        }

        $reference = $this->resolveComparisonReference($this->toComparisonReferenceInput($lap));

        // No record/runner-up lap exists yet (e.g. this is the very first lap ever recorded for
        // a freshly-forked race_type/checkpoint-count map variant) — still show this lap's own
        // splits rather than collapsing "no reference lap" into the same "no split data" message
        // as "this lap genuinely has no splits."
        return $reference
            ? LapTimeSplit::compare($lap['lapId'], $reference['lapId'])
            : LapTimeSplit::solo($lap['lapId']);
    }

    /**
     * Which lap is actually being compared against for the selected row — the map's record lap
     * for everyone except the record holder themselves, who gets that map's runner-up (2nd
     * fastest) instead (the record-lap equivalent of "#1 vs #2"). lap-vs-record-modal.blade.php
     * reads this dynamically rather than always showing the record holder's info, which
     * previously stayed on-screen even when the real comparison had silently swapped to the
     * runner-up — a mismatch that made the "no delta comparison" message look backwards.
     *
     * @return array{lapId: int, name: string, time: string, label: string}|null
     */
    public function getComparisonReferenceProperty(): ?array
    {
        $lap = $this->latestLaps[$this->selectedPlayerIndex] ?? $this->latestLaps[0] ?? null;

        return $this->resolveComparisonReference($this->toComparisonReferenceInput($lap));
    }

    /**
     * `$latestLaps`' rows are stored as `array<string, mixed>` (see `formatLatestLapRow()`'s own
     * docblock for why that's kept loose rather than a literal shape — `LengthAwarePaginator`'s
     * `TValue` template isn't covariant, so a shaped return type there doesn't unify with this
     * method's own declared generic), but every row is always actually built by
     * `formatLatestLapRow()`, which unconditionally sets exactly these five keys with exactly
     * these types — this narrows a known-good row down to the shape
     * `HasRecordVsRunnerUpReference::resolveComparisonReference()` declares it needs, without
     * loosening that trait method's own parameter type.
     *
     * @param  array<string, mixed>|null  $lap
     * @return array{lapId: int, mapId: int, recordLapId: ?int, recordHolder: string, recordTime: string}|null
     */
    private function toComparisonReferenceInput(?array $lap): ?array
    {
        if ($lap === null) {
            return null;
        }

        return [
            'lapId' => (int) $lap['lapId'],
            'mapId' => (int) $lap['mapId'],
            'recordLapId' => $lap['recordLapId'] !== null ? (int) $lap['recordLapId'] : null,
            'recordHolder' => (string) $lap['recordHolder'],
            'recordTime' => (string) $lap['recordTime'],
        ];
    }

    public function render(): View
    {
        return view('livewire.servers.server-show', [
            'laps' => $this->laps(),
            // Distinct page name ('players') so this doesn't collide with Latest Laps' own
            // pagination, which uses the default 'page' name on the same component.
            'rankedPlayers' => $this->rankedPlayers('players'),
        ])->layoutData([
            'title' => $this->serverName,
            'description' => "Maps, stats, and top players on {$this->serverName}, a Halo Race Leaderboard server.",
        ]);
    }
}
