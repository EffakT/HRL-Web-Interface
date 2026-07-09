<?php

namespace App\Livewire\Players;

use App\Livewire\Concerns\HasLapDetailModal;
use App\Livewire\Concerns\HasRecordVsRunnerUpReference;
use App\Models\GlobalRanking;
use App\Models\LapTimeSplit;
use App\Models\Player;
use App\Models\PlayerProfile;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Player', 'active' => 'players'])]
class PlayerShow extends Component
{
    use HasLapDetailModal, HasRecordVsRunnerUpReference;

    public string $playerId;

    public string $playerName;

    /** @var array<string, mixed> */
    public array $playerInfo = [];

    /** @var array<string, mixed> */
    public array $statsCard = [];

    /** @var array<int, string> */
    public array $achievements = [];

    /** @var array<int, array{mapId: int, lapId: int, recordLapId: ?int, map: string, server: string, time: string, date: string, dateExact: string, recordHolder: string, recordTime: string, mapRank: ?int, points: ?int}> */
    public array $laps = [];

    /** Keys into $laps for the Performance by Map table, in per-map order.
     *
     * @var array<int, int>
     */
    public array $performanceKeys = [];

    /** Keys into $laps for the Recent Laps feed, in reverse-chronological order.
     *
     * @var array<int, int>
     */
    public array $recentLapKeys = [];

    /** @var list<array<string, mixed>> */
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
     *
     * A parameterless wrapper, not the `#[On(...)]` attribute directly on `loadProfile()`:
     * Livewire's Echo bridge dispatches the broadcast payload as this listener's first argument,
     * which would hit `loadProfile(?Player $player)`'s type hint with an array/object instead of
     * a `Player`.
     */
    #[On('echo:activity,.lap.submitted')]
    public function onLapSubmitted(): void
    {
        $this->loadProfile();
    }

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

        // Keyed lookup rather than `$group->first()->server->name` below — Collection::first()
        // is typed nullable (it can't know a groupBy() result is never empty), and every group
        // here shares one server_id, so this is equivalent without going through a nullable type.
        $serverNamesById = $allLaps->pluck('server.name', 'server_id');

        $this->favServers = array_values($allLaps->groupBy('server_id')
            ->map(fn ($group, $serverId) => [
                'serverId' => (int) $serverId,
                'server' => $serverNamesById[$serverId],
                'laps' => $group->count(),
                'bestRank' => $bestRankByServer[$serverId] ?? null,
            ])
            ->sortByDesc('laps')
            ->all());
    }

    /**
     * Real per-checkpoint split comparison against the map's course-record lap — same pattern
     * as ServerShow, via the shared HasRecordVsRunnerUpReference trait.
     *
     * @return array<int, array{label: string, myTime: string, refTime: ?string, delta: ?string, deltaValue: ?float, running: ?string, faster: ?bool, absDelta: ?float, colorClass: string, barW: ?int, hasReference: bool, usingReferenceSplits: bool}>
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

    /** @return array{lapId: int, name: string, time: string, label: string}|null */
    public function getComparisonReferenceProperty(): ?array
    {
        $lap = $this->laps[$this->selectedPlayerIndex] ?? $this->laps[0] ?? null;

        return $this->resolveComparisonReference($lap);
    }

    public function render(): View
    {
        return view('livewire.players.player-show');
    }
}
