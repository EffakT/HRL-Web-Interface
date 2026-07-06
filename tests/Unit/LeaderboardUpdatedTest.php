<?php

use App\Events\LeaderboardUpdated;
use Illuminate\Broadcasting\Channel;

it('broadcasts on both the server-scoped and map-only public channels', function () {
    $event = new LeaderboardUpdated(
        serverId: 5,
        mapId: 1,
        playerId: 42,
        playerName: 'Effakt',
        time: 47.27,
        position: 1,
    );

    $channels = collect($event->broadcastOn())->map(fn (Channel $channel): string => $channel->name);

    expect($channels)->toContain('servers.5.maps.1')
        ->and($channels)->toContain('maps.1')
        ->and($channels)->toHaveCount(2);
});

it('broadcasts as leaderboard.updated with a snake_case payload', function () {
    $event = new LeaderboardUpdated(
        serverId: 5,
        mapId: 1,
        playerId: 42,
        playerName: 'Effakt',
        time: 47.27,
        position: 3,
    );

    expect($event->broadcastAs())->toBe('leaderboard.updated')
        ->and($event->broadcastWith())->toBe([
            'player_id' => 42,
            'player_name' => 'Effakt',
            'time' => 47.27,
            'position' => 3,
        ]);
});
