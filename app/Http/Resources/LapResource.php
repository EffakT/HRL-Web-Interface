<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LapResource extends JsonResource
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
            'server' => new ServerResource($this->server),
            'map' => new MapResource($this->map),
            'player' => $this->player,
            'time' => $this->time,
            'date' => $this->updated_at
        ];

        return $data;
    }
}
