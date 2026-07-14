<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * Wraps a plain array (built in PlayerController@index), not an Eloquent Player model — same
 * "resource wraps plain derived data" pattern ServerResource/MapResource already use, since
 * `rank`/`score`/`records` are computed aggregates from App\Models\GlobalRanking::scores(), not
 * real columns.
 *
 * Expects: array{id: int, rank: int, name: string, score: int, records: int, mapsPlayed: int, totalLaps: int, lastActiveAt: ?Carbon}
 */
class PlayerResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'rank' => $this->resource['rank'],
            'name' => $this->resource['name'],
            'score' => $this->resource['score'],
            'records' => $this->resource['records'],
            'maps_played' => $this->resource['mapsPlayed'],
            'total_laps' => $this->resource['totalLaps'],
            'last_active_at' => $this->resource['lastActiveAt']?->toIso8601String(),
        ];
    }
}
