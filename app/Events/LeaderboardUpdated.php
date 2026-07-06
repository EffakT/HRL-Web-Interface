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
 * Wired up to Reverb/Echo (roadmap item 16, see docs/database.md's "Live leaderboard updates"
 * section) — `ServerMapLeaderboard` (nested) and `MapLeaderboard` (global) both listen and
 * re-fetch their ranking on receipt. Public channels throughout: this whole site is already a
 * fully public leaderboard, so there's nothing to authorize.
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
