<?php

namespace App\Console\Commands;

use App\Helpers\GameServerQuery;
use App\Models\Map;
use App\Models\Server;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Roadmap item 19 — periodically live-queries every active server over the UDP `QueryServer`
 * protocol (see docs/database.md) and stores the result on the `servers` row, rather than
 * live-fetching on every page request. A UDP query has real latency and can time out if a
 * server's offline — unlike this app's other "derive fresh in PHP" calculators, blocking a
 * page render on that (or silently eating the latency on every request) would be worse than a
 * periodically-refreshed value.
 *
 * A failed query is non-fatal to the stored data — `current_map_id`/`live_player_count` are
 * left at their last-known values, only `queried_at`/`query_successful` update, so a single
 * transient network hiccup doesn't wipe good data. Consumers should treat `query_successful`
 * (and how stale `queried_at` is) as the signal for whether to trust the stored values or fall
 * back to a lap-history-derived proxy.
 */
#[Signature('app:refresh-live-server-info')]
#[Description('Live-query every active server for its current map and player count')]
class RefreshLiveServerInfo extends Command
{
    public function handle(GameServerQuery $query): int
    {
        foreach (Server::all() as $server) {
            $response = $query->query($server->ip, (int) $server->port);

            if ($response === false) {
                $server->update(['queried_at' => now(), 'query_successful' => false]);
                $this->line("✗ {$server->name}: {$query->getError()}");

                continue;
            }

            // Deliberately not fabricated: if `mapname` doesn't match any known Map row, leave
            // current_map_id null rather than creating one — a Map row is only ever created
            // from an actual lap submission (see ProcessNewLap), not a live query.
            $currentMapId = isset($response['mapname'])
                ? Map::where('name', $response['mapname'])->value('id')
                : null;

            $numPlayers = isset($response['numplayers']) ? (int) $response['numplayers'] : null;

            $server->update([
                'current_map_id' => $currentMapId,
                'live_player_count' => $numPlayers,
                'queried_at' => now(),
                'query_successful' => true,
            ]);

            $this->line("✓ {$server->name}: {$response['mapname']} ({$numPlayers} players)");
        }

        return self::SUCCESS;
    }
}
