<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired only when a submitted lap is a genuine personal-best/course-record-worthy
 * improvement (not on every logged attempt) — see docs/database.md's "Webhook → job flow".
 * Public channels throughout: this whole site is already a fully public leaderboard, so there's
 * nothing to authorize.
 *
 * **No direct Livewire consumer as of 2026-07-08.** `ServerMapLeaderboard` and `MapLeaderboard`
 * originally listened on this event's channels to re-fetch their ranking, but that left their
 * `$totalLaps` figure stale after every non-improving lap (this event never fires for one) — both
 * were retargeted to listen on `App\Events\LapSubmitted`'s site-wide `activity` channel instead,
 * which is a strict superset of when this event fires (see `ProcessNewLap`) and covers both
 * cases with one listener. Still dispatched and broadcast (see `ProcessNewLap`) since it carries
 * genuinely distinct data (the specific player/time/position of the improvement, not just "a lap
 * happened somewhere") that a future PB-specific feature (e.g. a toast/celebration on the exact
 * page watching that map) could consume directly instead of re-deriving it from a full reload.
 */
class LeaderboardUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $serverId,
        public int $mapId,
        public int $playerId,
        public string $playerName,
        public float $time,
        public int $position,
    ) {}

    /**
     * Two channels: the server-scoped one (`ServerMapLeaderboard`'s nested ranking, unaffected
     * by other servers) and a map-only one (`MapLeaderboard`'s global ranking, which a PB on
     * *any* server for this map can change). A single event fired once is broadcast on both —
     * cheaper than firing two separate events for what's fundamentally one occurrence.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("servers.{$this->serverId}.maps.{$this->mapId}"),
            new Channel("maps.{$this->mapId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'leaderboard.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'player_id' => $this->playerId,
            'player_name' => $this->playerName,
            'time' => $this->time,
            'position' => $this->position,
        ];
    }
}
