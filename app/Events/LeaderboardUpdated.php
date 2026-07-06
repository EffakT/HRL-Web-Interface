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
 * Broadcasting infrastructure (Reverb/Echo, roadmap item 16) isn't wired up yet, so with the
 * default `log` broadcast connection this currently just writes to the log — no frontend
 * listens yet. Public channel: this whole site is already a fully public leaderboard.
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

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new Channel("servers.{$this->serverId}.maps.{$this->mapId}"),
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
