<?php

namespace App\Livewire\Servers;

use App\Livewire\Concerns\HasLapDetailModal;
use App\Livewire\Concerns\HasRankedLeaderboardPagination;
use App\Models\LapTime;
use App\Models\LapTimeSplit;
use App\Models\Map;
use App\Models\Server;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Nested leaderboard: best laps for ONE map, scoped to ONE server.
 * Reached via Servers -> a server's map list -> this page.
 * For the global (all-servers) leaderboard, see App\Livewire\Maps\MapLeaderboard.
 */
#[Layout('components.layout', ['title' => 'Map Leaderboard', 'active' => 'servers'])]
class ServerMapLeaderboard extends Component
{
    use HasLapDetailModal, HasRankedLeaderboardPagination;

    public string $serverParam;

    public string $mapParam;

    public string $server;

    public string $map;

    public int $totalLaps = 0;

    public array $players = [];

    public array $splits = [];

    /** The #1 player's lap id — used both for the split-pace sparkline and as the comparison reference for every other player. */
    public ?int $topLapId = null;

    public function mount(string $serverId, string $mapId): void
    {
        $server = Server::findOrFail($serverId);
        $map = Map::findOrFail($mapId);

        $this->serverParam = $serverId;
        $this->mapParam = $mapId;
        $this->server = $server->name;
        $this->map = $map->label;

        $this->loadLeaderboard($server, $map);
    }

    /**
     * Live update (roadmap item 16) — a genuine PB/record on this exact server+map. The channel
     * name embeds the component's own `serverParam`/`mapParam` properties, so Livewire only
     * subscribes to this specific pairing, not every server's updates for this map (that's
     * `MapLeaderboard`'s `maps.{mapId}` channel instead — see App\Events\LeaderboardUpdated).
     */
    #[On('echo-public:servers.{serverParam}.maps.{mapParam},leaderboard.updated')]
    public function onLeaderboardUpdated(): void
    {
        $this->loadLeaderboard(Server::findOrFail($this->serverParam), Map::findOrFail($this->mapParam));
    }

    private function loadLeaderboard(Server $server, Map $map): void
    {
        // Ranked leaderboard: one row per player, their best lap on this server+map. Ties go to
        // whoever set the time earliest (same tie-break precedent as global-ranking.md) — sorting
        // by time then created_at before taking the first occurrence per player achieves this.
        $laps = LapTime::where('server_id', $server->id)
            ->where('map_id', $map->id)
            ->with('player')
            ->orderBy('time')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $this->totalLaps = $laps->count();

        $bestPerPlayer = $laps->unique('player_id')->values();
        $topTime = $bestPerPlayer->first()?->time;
        $secondTime = $bestPerPlayer->get(1)?->time;
        $this->topLapId = $bestPerPlayer->first()?->id;

        $this->players = $bestPerPlayer
            ->map(function (LapTime $lap, int $index) use ($topTime, $secondTime): array {
                // #1's "gap" is to #2 (the only other lap it's meaningful to compare against) —
                // there's no "gap to record" for the record holder itself. Every gap renders
                // with a "+" sign regardless of direction (lead or deficit); the modal colors
                // it green/red based on which one it actually is (see lap-detail-modal.blade.php).
                $gap = $index === 0
                    ? ($secondTime !== null ? '+'.number_format((float) $secondTime - (float) $topTime, 3) : '—')
                    : '+'.number_format((float) $lap->time - (float) $topTime, 3);

                return [
                    'lapId' => $lap->id,
                    'playerId' => $lap->player_id,
                    'rank' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                    'name' => $lap->player->name,
                    'subtitle' => '',
                    'time' => $lap->formattedTime(),
                    'gap' => $gap,
                    'date' => $lap->created_at->diffForHumans(),
                    'dateExact' => $lap->created_at->format('d M Y, H:i').' '.$lap->created_at->format('T'),
                ];
            })
            ->all();

        // Split-pace sparkline for the #1 lap — empty if that lap has no recorded splits (real
        // coverage is sparse, see docs/database.md); the podium partial handles an empty array fine.
        $this->splits = $this->topLapId
            ? LapTimeSplit::where('lap_time_id', $this->topLapId)
                ->orderBy('checkpoint_id')
                ->get()
                ->map(fn (LapTimeSplit $split, int $index): array => [
                    'label' => 'CP '.($index + 1),
                    'time' => number_format($split->duration, 3),
                ])
                ->all()
            : [];
    }

    /**
     * Real per-checkpoint split comparison against the #1 player's lap — same shared math as
     * ServerShow, see LapTimeSplit::compare(). Frequently empty (sparse real split data).
     * When viewing #1's own lap, there's nothing meaningful to compare it against itself, so
     * it's compared against #2 instead — see resolveComparisonReference().
     */
    public function getComparisonProperty(): array
    {
        $player = $this->players[$this->selectedPlayerIndex] ?? $this->players[0] ?? null;
        $reference = $this->resolveComparisonReference($player);

        if (! $player || ! $reference) {
            return [];
        }

        return LapTimeSplit::compare($player['lapId'], $reference['lapId']);
    }

    /**
     * Which player is actually being compared against for the selected lap — #1 for everyone
     * except #1 themselves, who gets #2 instead (see getComparisonProperty()). The "vs RECORD"
     * label in lap-detail-modal.blade.php reads this dynamically rather than always showing
     * player #1's info, which previously stayed on-screen even when the real comparison had
     * silently swapped to #2 — a mismatch that made the "this lap has splits, reference
     * doesn't" message look backwards when viewing #1 (reported by the user).
     */
    public function getComparisonReferenceProperty(): ?array
    {
        $player = $this->players[$this->selectedPlayerIndex] ?? $this->players[0] ?? null;

        return $this->resolveComparisonReference($player);
    }

    private function resolveComparisonReference(?array $player): ?array
    {
        if (! $player) {
            return null;
        }

        if ($player['lapId'] === $this->topLapId) {
            $reference = $this->players[1] ?? null;

            return $reference ? [...$reference, 'label' => 'RUNNER-UP'] : null;
        }

        $reference = $this->players[0] ?? null;

        return $reference ? [...$reference, 'label' => 'RECORD'] : null;
    }

    public function render()
    {
        return view('livewire.servers.server-map-leaderboard', [
            'rankedPlayers' => $this->rankedPlayers(),
        ]);
    }
}
