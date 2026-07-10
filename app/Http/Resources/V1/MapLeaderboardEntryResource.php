<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** Wraps one row of App\Models\GlobalRanking::mapLeaderboard()'s plain-array output. */
class MapLeaderboardEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'rank' => $this->resource['rank'],
            'lap_id' => $this->resource['lapId'],
            'player' => [
                'id' => $this->resource['playerId'],
                'name' => $this->resource['playerName'],
            ],
            'server' => [
                'id' => $this->resource['serverId'],
                'name' => $this->resource['serverName'],
            ],
            'time' => $this->resource['timeRaw'],
            'time_formatted' => $this->resource['time'],
            'gap' => $this->resource['gapRaw'],
            'set_at' => $this->resource['setAt']?->toIso8601String(),
        ];
    }
}
