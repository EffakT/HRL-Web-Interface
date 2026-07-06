<?php

namespace App\Livewire;

use App\Models\GlobalRanking;
use App\Models\LapTime;
use App\Models\Map;
use App\Models\MostActiveServer;
use App\Models\Player;
use App\Models\RecordHistory;
use App\Models\Server;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Home', 'active' => 'home'])]
class Home extends Component
{
    /** Recency window shared by every windowed highlight below — see docs/homepage.md's "needs confirming" note. */
    private const RECENCY_DAYS = 7;

    public array $highlights = [];

    public array $quickStats = [];

    public function mount(): void
    {
        // Six candidate highlight blocks, keyed by type — an empty array means "nothing to show
        // this round" (the fixed-priority selection below skips it), same contract as the mock
        // version.
        $candidates = [
            'records' => $this->latestRecords(),
            'most-active-server' => $this->mostActiveServers(),
            'fastest-improvements' => $this->fastestImprovements(),
            'new-content' => $this->newContent(),
            'achievements' => $this->achievements(),
            'live-stats' => $this->liveStats(),
        ];

        $priority = ['records', 'most-active-server', 'fastest-improvements', 'new-content', 'achievements', 'live-stats'];

        $this->highlights = collect($priority)
            ->map(fn (string $type): array => ['type' => $type, 'data' => $candidates[$type]])
            ->filter(fn (array $block): bool => ! empty($block['data']))
            ->take(3)
            ->values()
            ->all();

        $this->quickStats = [
            'players' => Player::count(),
            'servers' => Server::count(),
            'laps' => LapTime::count(),
        ];
    }

    /**
     * Most recent real record-breaking events (App\Models\RecordHistory, roadmap item 13),
     * within the recency window. A genuine point-in-time derivation — this isn't "current course
     * records," it's "when did a lap actually become the new fastest time on its map," which is
     * why it needed its own calculator rather than reusing GlobalRanking's excludeLapId trick
     * (see docs/decisions.md).
     *
     * @return list<array{map: string, time: string, player: string, server: string, ago: string}>
     */
    private function latestRecords(): array
    {
        return collect(RecordHistory::recent(3, self::RECENCY_DAYS))
            ->map(fn (array $event): array => [
                'map' => $event['map'],
                'time' => $event['time'],
                'player' => $event['playerName'],
                'server' => $event['serverName'],
                'ago' => $event['setAt'] !== null ? $event['setAt']->diffForHumans() : '—',
            ])
            ->all();
    }

    /**
     * Top 3 servers by real Activity Score (App\Models\MostActiveServer, roadmap item 12),
     * podium-style per docs/homepage.md. Only servers with genuine activity (totalScore > 0)
     * qualify — a server with zero real engagement in the last 90 days has nothing to show,
     * same "empty rather than fake" rule as every other highlight here.
     *
     * @return list<array{rank: int, name: string, players30d: int, players90d: int, lastActive: string}>
     */
    private function mostActiveServers(): array
    {
        return collect(MostActiveServer::scores())
            ->filter(fn (array $server): bool => $server['totalScore'] > 0)
            ->take(3)
            ->map(fn (array $server): array => [
                'rank' => $server['rank'],
                'name' => $server['name'],
                'players30d' => $server['players30d'],
                'players90d' => $server['players90d'],
                'lastActive' => $server['lastLapAt'] !== null ? $server['lastLapAt']->diffForHumans() : '—',
            ])
            ->values()
            ->all();
    }

    /**
     * Three sub-items — biggest PB improvement, largest per-map rank jump, and a new global
     * Top 10/Top 3 entry — all derived from this week's real laps. None of these need stored
     * historical snapshots: each re-runs the current ranking with one lap excluded and compares
     * to the real current state, exactly the technique docs/homepage.md describes.
     *
     * @return list<array{text: string}>
     */
    private function fastestImprovements(): array
    {
        $recentLaps = LapTime::whereHas('server')
            ->where('created_at', '>=', now()->subDays(self::RECENCY_DAYS))
            ->with(['player', 'map'])
            ->get();

        if ($recentLaps->isEmpty()) {
            return [];
        }

        $items = [];
        $usedLapIds = [];

        // 1. Biggest PB improvement — delta vs. the lap immediately preceding it for the same
        // player+map+server. Matches how these rows actually came to exist historically (each
        // insert was itself a fresh improvement over whatever came right before it — see
        // docs/database.md); a negative "delta" (possible once the rebuilt webhook logs every
        // attempt, not just improvements) isn't a real improvement, so those are excluded.
        //
        // Gated on the RESULTING rank actually earning points (rank ≤50 — reusing
        // GlobalRanking::pointsForRank()'s existing cutoff rather than inventing a new
        // threshold). Without this, the metric is trivially gameable: submit a deliberately
        // terrible first lap on a map (nothing to beat yet, so it's automatically your "PB"),
        // then a merely-average one — the delta looks huge even though the actual result is
        // still nowhere near competitive. Confirmed on real data: an "81s improvement" landed a
        // player at map rank #70, which earns zero real points — not a genuine highlight.
        $biggestImprovement = $recentLaps
            ->map(function (LapTime $lap): ?array {
                $previous = LapTime::where('player_id', $lap->player_id)
                    ->where('map_id', $lap->map_id)
                    ->where('server_id', $lap->server_id)
                    ->where('id', '<', $lap->id)
                    ->orderByDesc('id')
                    ->first();

                if (! $previous) {
                    return null;
                }

                $delta = (float) $previous->time - (float) $lap->time;
                $rank = GlobalRanking::mapRank($lap->map_id, $lap->player_id);

                if ($delta <= 0 || ! $rank || GlobalRanking::pointsForRank($rank) === 0) {
                    return null;
                }

                return ['lap' => $lap, 'delta' => $delta, 'rank' => $rank];
            })
            ->filter()
            ->sortByDesc('delta')
            ->first();

        if ($biggestImprovement) {
            $lap = $biggestImprovement['lap'];
            $usedLapIds[] = $lap->id;

            $items[] = [
                'text' => "{$lap->player->name} improved {$lap->map->label} by ".number_format($biggestImprovement['delta'], 2).'s'
                    ." (now #{$biggestImprovement['rank']}).",
            ];
        }

        // 2. Largest rank jump on a map — re-run that map's ranking with this lap excluded
        // (the player's standing immediately before it) vs. their real current rank. Prefers a
        // lap not already used above — a big enough improvement almost always causes the
        // biggest jump too, and showing the same event twice in one block reads as repetitive
        // rather than "3 different things happening." Only reuses it if it's genuinely the only
        // real candidate this week (see docs/decisions.md). Same points-earning gate as sub-item
        // 1 above, for the same reason — a jump from "very last" to "still not competitive"
        // isn't a real highlight just because the rank delta number is big.
        $jumpCandidates = $recentLaps
            ->map(function (LapTime $lap): ?array {
                $newRank = GlobalRanking::mapRank($lap->map_id, $lap->player_id);
                $oldRank = GlobalRanking::mapRank($lap->map_id, $lap->player_id, excludeLapId: $lap->id);

                if (! $newRank || ! $oldRank || $oldRank <= $newRank || GlobalRanking::pointsForRank($newRank) === 0) {
                    return null;
                }

                return ['lap' => $lap, 'oldRank' => $oldRank, 'newRank' => $newRank, 'jump' => $oldRank - $newRank];
            })
            ->filter()
            ->sortByDesc('jump');

        $biggestJump = $jumpCandidates->reject(fn (array $c) => in_array($c['lap']->id, $usedLapIds, true))->first()
            ?? $jumpCandidates->first();

        if ($biggestJump) {
            $lap = $biggestJump['lap'];
            $usedLapIds[] = $lap->id;
            $items[] = [
                'text' => "{$lap->player->name} jumped from #{$biggestJump['oldRank']} to #{$biggestJump['newRank']} on {$lap->map->label}.",
            ];
        }

        // 3. New entry into the global Top 10 (or Top 3) — same before/after technique, applied
        // to Global Score instead of one map. Prefers a lap not already used above, same
        // distinct-story preference as sub-item 2. Also listed under Achievements' "first
        // appearance in Top 10/Top 3" — the same real event can legitimately surface in both
        // blocks if both are selected in the same load; not deduplicated across blocks, see
        // docs/decisions.md.
        $topEntryCandidates = $recentLaps
            ->unique('player_id')
            ->map(function (LapTime $lap): ?array {
                $current = GlobalRanking::forPlayer($lap->player_id);
                $before = GlobalRanking::forPlayer($lap->player_id, excludeLapId: $lap->id);
                $newRank = $current['rank'] ?? null;
                $oldRank = $before['rank'] ?? null;

                if (! $newRank || $newRank > 10 || ($oldRank !== null && $oldRank <= 10)) {
                    return null;
                }

                return ['lapId' => $lap->id, 'name' => $current['name'], 'newRank' => $newRank];
            })
            ->filter()
            ->sortBy('newRank');

        $newTopEntry = $topEntryCandidates->reject(fn (array $c) => in_array($c['lapId'], $usedLapIds, true))->first()
            ?? $topEntryCandidates->first();

        if ($newTopEntry) {
            $tier = $newTopEntry['newRank'] <= 3 ? 'Top 3' : 'Top 10';
            $items[] = ['text' => "{$newTopEntry['name']} entered the global {$tier} for the first time."];
        }

        return $items;
    }

    /** @return list<array{type: string, name: string, ago: string}> */
    private function newContent(): array
    {
        $cutoff = now()->subDays(self::RECENCY_DAYS);

        $maps = Map::where('created_at', '>=', $cutoff)->get()
            ->map(fn (Map $map): array => ['type' => 'map', 'name' => $map->label, 'ago' => $map->created_at->diffForHumans(), 'at' => $map->created_at]);

        $servers = Server::where('created_at', '>=', $cutoff)->get()
            ->map(fn (Server $server): array => ['type' => 'server', 'name' => $server->name, 'ago' => $server->created_at->diffForHumans(), 'at' => $server->created_at]);

        return $maps->concat($servers)
            ->sortByDesc('at')
            ->map(fn (array $entry): array => ['type' => $entry['type'], 'name' => $entry['name'], 'ago' => $entry['ago']])
            ->values()
            ->all();
    }

    /**
     * First-ever course records, lap-count milestones, and first Top 10/Top 3 appearances, for
     * players active this week — in that priority order per docs/homepage.md.
     *
     * @return list<array{player: string, note: string}>
     */
    private function achievements(): array
    {
        $recentLaps = LapTime::whereHas('server')
            ->where('created_at', '>=', now()->subDays(self::RECENCY_DAYS))
            ->with('player')
            ->get()
            ->unique('player_id');

        if ($recentLaps->isEmpty()) {
            return [];
        }

        $items = [];

        // 1. First-ever course record — App\Models\RecordHistory's chronological replay tells us
        // exactly which lap was each player's first, so this checks whether one of this week's
        // laps *is* that lap (real data added 2026-07-06, previously skipped for lack of this).
        $firstRecordByPlayer = collect(RecordHistory::events())->unique('playerId')->keyBy('playerId');

        foreach ($recentLaps as $lap) {
            if (count($items) >= 3) {
                break;
            }

            $firstRecord = $firstRecordByPlayer[$lap->player_id] ?? null;

            if ($firstRecord && $firstRecord['lapId'] === $lap->id) {
                $items[] = ['player' => $lap->player->name, 'note' => "set their first-ever course record on {$firstRecord['map']}"];
            }
        }

        // 2. Lap-count milestones, calibrated to this project's real (small) scale — the most
        // laps any real player has ever raced is in the dozens as of 2026-07-06, not the
        // thousands a generic "1,000 laps" milestone would assume. See docs/decisions.md.
        $milestones = [10, 25, 50, 100, 250, 500, 1000];

        foreach ($recentLaps as $lap) {
            if (count($items) >= 3) {
                break;
            }

            $totalNow = LapTime::where('player_id', $lap->player_id)->whereHas('server')->count();
            $totalBefore = $totalNow - 1;

            $crossed = collect($milestones)->first(fn (int $m): bool => $totalBefore < $m && $totalNow >= $m);

            if ($crossed) {
                $items[] = ['player' => $lap->player->name, 'note' => "crossed {$crossed} total laps"];
            }
        }

        // 3. First appearance in the global Top 10/Top 3.
        foreach ($recentLaps as $lap) {
            if (count($items) >= 3) {
                break;
            }

            $current = GlobalRanking::forPlayer($lap->player_id);
            $before = GlobalRanking::forPlayer($lap->player_id, excludeLapId: $lap->id);
            $newRank = $current['rank'] ?? null;
            $oldRank = $before['rank'] ?? null;

            if ($newRank && $newRank <= 10 && ($oldRank === null || $oldRank > 10)) {
                $tier = $newRank <= 3 ? 'Top 3' : 'Top 10';
                $items[] = ['player' => $current['name'], 'note' => "first appearance in the global {$tier}"];
            }
        }

        return array_slice($items, 0, 3);
    }

    /** @return array{totalLaps: int, activePlayers30d: int, activePlayers90d: int, activeServers30d: int, activeServers90d: int, mapsToday: int, mapsThisWeek: int} */
    private function liveStats(): array
    {
        $activeLaps = fn () => LapTime::whereHas('server');

        return [
            'totalLaps' => LapTime::count(),
            'activePlayers30d' => $activeLaps()->where('created_at', '>=', now()->subDays(30))->distinct('player_id')->count('player_id'),
            'activePlayers90d' => $activeLaps()->where('created_at', '>=', now()->subDays(90))->distinct('player_id')->count('player_id'),
            'activeServers30d' => $activeLaps()->where('created_at', '>=', now()->subDays(30))->distinct('server_id')->count('server_id'),
            'activeServers90d' => $activeLaps()->where('created_at', '>=', now()->subDays(90))->distinct('server_id')->count('server_id'),
            'mapsToday' => $activeLaps()->whereDate('created_at', now()->toDateString())->distinct('map_id')->count('map_id'),
            'mapsThisWeek' => $activeLaps()->where('created_at', '>=', now()->subDays(7))->distinct('map_id')->count('map_id'),
        ];
    }

    public function render()
    {
        return view('livewire.home');
    }
}
