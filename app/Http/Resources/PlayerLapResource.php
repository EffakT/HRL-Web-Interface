<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PlayerLapResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $data = [
            'id' => $this->id,
            'time' => $this->time,
            'date' => $this->updated_at,
            'map' => new MapResource($this->map),
            'server' => new LapServerResource($this->server),
        ];

        return $data;
    }
}
