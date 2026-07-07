<?php

namespace App\Livewire\Servers;

use App\Livewire\Concerns\HasLapDetailModal;
use App\Livewire\Concerns\HasRecordVsRunnerUpReference;
use App\Models\GlobalRanking;
use App\Models\LapTimeSplit;
use App\Models\Player;
use App\Models\PlayerProfile;
use App\Models\Server;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * A player's profile scoped to one server — reached by clicking a player from Server Single's
 * Top Players list. Shows Server Rank/Score alongside Global Rank/Score for context (rather
 * than only one or the other), and every stat (Performance by Map, Recent Laps, Stats Card)
 * isolated to laps on this server — plus a link out to the full global profile
 * (App\Livewire\Players\PlayerShow) for anyone who wants the complete picture. See
 * docs/player-single.md and docs/decisions.md.
 */
#[Layout('components.layout', ['title' => 'Player', 'active' => 'servers'])]
class ServerPlayerShow extends Component
{
    use HasLapDetailModal, HasRecordVsRunnerUpReference;

    public int $serverId;

    public string $serverName;

    public string $serverIp;

    public string $serverPort;

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

    public function mount(string $serverId, string $playerId): void
    {
        $server = Server::findOrFail($serverId);
        $player = Player::findOrFail($playerId);

        $this->serverId = $server->id;
        $this->serverName = $server->name;
        $this->serverIp = $server->ip;
        $this->serverPort = $server->port;
        $this->playerId = $playerId;
        $this->playerName = $player->name;

        $this->loadProfile($server, $player);
    }

    /**
     * Live update (roadmap item 16 follow-up) — listens on the site-wide `activity` channel
     * (matching ServerList/Home) rather than a per-map one, since any lap by this player on this
     * server, or any other lap changing the server-scoped ranking, can move these numbers.
     */
    #[On('echo-public:activity,lap.submitted')]
    public function loadProfile(?Server $server = null, ?Player $player = null): void
    {
        $server ??= Server::findOrFail($this->serverId);
        $player ??= Player::findOrFail($this->playerId);

        $serverRanking = GlobalRanking::forPlayer($player->id, $server->id);

        // Reached by clicking a real row in Server Single's Top Players list, so this player is
        // always ranked on this server in practice — abort rather than render an empty profile
        // for a direct URL hit on a player who's never actually raced here.
        abort_if($serverRanking === null, 404);

        $globalRanking = GlobalRanking::forPlayer($player->id);

        $this->playerInfo = [
            'serverRank' => $serverRanking['rank'],
            'serverScore' => $serverRanking['score'],
            'globalRank' => $globalRanking['rank'] ?? '—',
            'globalScore' => $globalRanking['score'] ?? 0,
        ];

        $profile = PlayerProfile::build($player, $serverRanking, $server->id);
        $this->laps = $profile['laps'];
        $this->performanceKeys = $profile['performanceKeys'];
        $this->recentLapKeys = $profile['recentLapKeys'];
        $this->statsCard = $profile['statsCard'];
        $this->achievements = $profile['achievements'];
    }

    /**
     * Real per-checkpoint split comparison against the map's course-record lap — same pattern
     * as PlayerShow/ServerShow, via the shared HasRecordVsRunnerUpReference trait.
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
        return view('livewire.servers.server-player-show');
    }
}
