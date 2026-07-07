<?php

namespace App\Livewire\Players;

use App\Livewire\Concerns\HasLapDetailModal;
use App\Livewire\Concerns\HasRecordVsRunnerUpReference;
use App\Models\GlobalRanking;
use App\Models\LapTimeSplit;
use App\Models\Player;
use App\Models\PlayerProfile;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Player', 'active' => 'players'])]
class PlayerShow extends Component
{
    use HasLapDetailModal, HasRecordVsRunnerUpReference;

    public string $playerId;

    public string $playerName;

    public array $playerInfo = [];

    public array $statsCard = [];

    public array $achievements = [];

    public array $laps = [];

    /** Keys into $laps for the Performance by Map table, in per-map order. */
    public array $performanceKeys = [];

    /** Keys into $laps for the Recent Laps feed, in reverse-chronological order. */
    public array $recentLapKeys = [];

    public array $favServers = [];

    public function mount(string $playerId): void
    {
        $player = Player::findOrFail($playerId);

        $this->playerId = $playerId;
        $this->playerName = $player->name;

        $this->loadProfile($player);
    }

    /**
     * Live update (roadmap item 16 follow-up) — Global Rank/Score and Fav Servers can change on
     * any lap by any player anywhere (not just this one), since the global ranking is relative,
     * so this listens on the site-wide `activity` channel (matching ServerList/Home) rather than
     * a per-player or per-map one.
     */
    #[On('echo-public:activity,lap.submitted')]
    public function loadProfile(?Player $player = null): void
    {
        $player ??= Player::findOrFail($this->playerId);

        $ranking = GlobalRanking::forPlayer($player->id);
        $perMap = collect($ranking['perMap'] ?? []);

        $this->playerInfo = [
            'globalRank' => $ranking['rank'] ?? '—',
            'globalScore' => $ranking['score'] ?? 0,
        ];

        $profile = PlayerProfile::build($player, $ranking);
        $this->laps = $profile['laps'];
        $this->performanceKeys = $profile['performanceKeys'];
        $this->recentLapKeys = $profile['recentLapKeys'];
        $this->achievements = $profile['achievements'];

        // All of this player's real laps (every attempt, not just per-map bests) — Fav Servers
        // needs full lap volume grouped by server, not just PB rows.
        $allLaps = $player->lapTimes()->whereHas('server')->with('server')->get();

        $this->statsCard = [
            ...$profile['statsCard'],
            'serversPlayed' => $allLaps->pluck('server_id')->unique()->count(),
        ];

        // Fav[orite] Servers — sorted by lap count descending (assumption, see
        // docs/player-single.md). bestRank is this player's best Map Rank among the maps whose
        // PB was set on that server; null (rendered as "—") if none of their per-map bests
        // happen to live on that server.
        $bestRankByServer = $perMap->groupBy('serverId')->map(fn ($group) => $group->min('rank'));

        $this->favServers = $allLaps->groupBy('server_id')
            ->map(fn ($group, $serverId) => [
                'serverId' => (int) $serverId,
                'server' => $group->first()->server->name,
                'laps' => $group->count(),
                'bestRank' => $bestRankByServer[$serverId] ?? null,
            ])
            ->sortByDesc('laps')
            ->values()
            ->all();
    }

    /**
     * Real per-checkpoint split comparison against the map's course-record lap — same pattern
     * as ServerShow, via the shared HasRecordVsRunnerUpReference trait.
     */
    public function getComparisonProperty(): array
    {
        $lap = $this->laps[$this->selectedPlayerIndex] ?? $this->laps[0] ?? null;
        $reference = $this->resolveComparisonReference($lap);

        if (! $lap || ! $reference) {
            return [];
        }

        return LapTimeSplit::compare($lap['lapId'], $reference['lapId']);
    }

    public function getComparisonReferenceProperty(): ?array
    {
        $lap = $this->laps[$this->selectedPlayerIndex] ?? $this->laps[0] ?? null;

        return $this->resolveComparisonReference($lap);
    }

    public function render()
    {
        return view('livewire.players.player-show');
    }
}
