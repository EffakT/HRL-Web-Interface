<?php

use App\Events\LapSubmitted;
use Illuminate\Broadcasting\Channel;

it('broadcasts on the site-wide public activity channel', function () {
    $event = new LapSubmitted(serverId: 5, mapId: 1);

    $channels = collect($event->broadcastOn())->map(fn (Channel $channel): string => $channel->name);

    expect($channels)->toContain('activity')
        ->and($channels)->toHaveCount(1);
});

it('broadcasts as lap.submitted with a snake_case payload', function () {
    $event = new LapSubmitted(serverId: 5, mapId: 1);

    expect($event->broadcastAs())->toBe('lap.submitted')
        ->and($event->broadcastWith())->toBe([
            'server_id' => 5,
            'map_id' => 1,
        ]);
});
