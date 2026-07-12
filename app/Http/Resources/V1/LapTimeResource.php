<?php

namespace App\Http\Resources\V1;

use App\Models\LapTime;
use App\Models\LapTimeSplit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin LapTime */
class LapTimeResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'time' => (float) $this->time,
            'time_formatted' => $this->formattedTime(),
            'player' => [
                'id' => $this->player_id,
                'name' => $this->player->name,
            ],
            'map' => [
                'id' => $this->map_id,
                'label' => $this->map->label,
            ],
            'server' => [
                'id' => $this->server_id,
                'name' => $this->server->name,
            ],
            'set_at' => $this->created_at?->toIso8601String(),
            // Sparse real coverage (~4% of laps have splits — see docs/database.md), so an
            // empty array here is the common case, not an error state.
            'splits' => $this->splits->map(fn (LapTimeSplit $split): array => [
                'checkpoint_id' => $split->checkpoint_id,
                'duration' => $split->duration,
            ])->values(),
        ];
    }
}
