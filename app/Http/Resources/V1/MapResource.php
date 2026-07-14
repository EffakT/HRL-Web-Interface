<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * Wraps a plain array (built in MapController@index), not an Eloquent Map model directly — same
 * "resource wraps plain derived data" pattern ServerResource already uses, since total_laps is a
 * computed aggregate, not a real column.
 *
 * Expects: array{id: int, name: string, label: string, checkpoint_count: ?int, total_laps: int}
 */
class MapResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'label' => $this->resource['label'],
            'checkpoint_count' => $this->resource['checkpoint_count'],
            'total_laps' => $this->resource['total_laps'],
        ];
    }
}
