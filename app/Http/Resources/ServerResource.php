<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ServerResource extends JsonResource
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
            'ip' => $this->ip,
            'port' => $this->port,
            'name' => $this->name,
            'created_at' => $this->created_at
        ];

        $data['latest_lap'] = new ServerLapResource($this->latest_lap);

        return $data;
    }
}
