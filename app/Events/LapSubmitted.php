<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired on *every* logged lap attempt (unlike App\Events\LeaderboardUpdated, which only fires
 * on a genuine PB/record) — for site-wide aggregates that change on any submission, not just an
 * improvement: Servers List's header stats/"MOST ACTIVE" card, Home's highlights, and (2026-07-08,
 * retargeted from the map-scoped `LeaderboardUpdated` channels) `MapLeaderboard`/
 * `ServerMapLeaderboard`'s "SHOWING X / Y LAPS" total, which needs every attempt, not just PBs.
 * A single site-wide public channel rather than a per-server/per-map one, since most consumers
 * aren't scoped to one server or map (the two leaderboard components are the exception — they
 * still filter to their own server+map when reloading, just via this broader trigger).
 * See docs/database.md's "Live leaderboard updates" section.
 */
class LapSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $serverId,
        public int $mapId,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new Channel('activity'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'lap.submitted';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'server_id' => $this->serverId,
            'map_id' => $this->mapId,
        ];
    }
}
