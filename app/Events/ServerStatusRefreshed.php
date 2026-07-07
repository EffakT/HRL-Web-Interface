<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once per `App\Console\Commands\RefreshLiveServerInfo` run (roadmap item 19), not per
 * server — that scheduled live-status poll didn't broadcast anything on its own, so an open
 * Servers List page would only pick up a status change (online/offline, current map, player
 * count) whenever an unrelated lap happened to be submitted somewhere and triggered
 * `ServerList`'s `lap.submitted` listener. A single site-wide signal after every server's been
 * queried is simpler than a per-server event, since `ServerList::loadServers()` always reloads
 * its full table regardless of which server changed.
 */
class ServerStatusRefreshed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new Channel('activity'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'server-status.refreshed';
    }
}
