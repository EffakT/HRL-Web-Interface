<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * Wraps a plain array (built in ServerController@index), not an Eloquent Server model — the
 * per-server stats are derived aggregates, same "resource wraps plain data" pattern used by
 * GlobalRanking/MostActiveServer/RecordHistory elsewhere in this app.
 *
 * Deliberately snake_case keys and a clean "name" — the old API's equivalent had a `"name "`
 * (trailing space) key bug; fixed here by construction, not by patching around it.
 *
 * Expects: array{id: int, name: string, totalLaps: int, totalPlayers: int, mapsPlayed: int, lastActiveAt: ?Carbon}
 */
class ServerResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'total_laps' => $this->resource['totalLaps'],
            'total_players' => $this->resource['totalPlayers'],
            'maps_played' => $this->resource['mapsPlayed'],
            'last_active_at' => $this->resource['lastActiveAt']?->toIso8601String(),
        ];
    }
}
