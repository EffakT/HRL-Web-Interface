<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ServerResource;
use App\Models\LapTime;
use App\Models\Server;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServerController extends Controller
{
    /** GET /api/v1/servers — every active (non-archived) server with real, derived stats. */
    public function index(): AnonymousResourceCollection
    {
        $servers = Server::all()->map(function (Server $server): array {
            $laps = LapTime::where('server_id', $server->id);
            $lastLap = (clone $laps)->orderByDesc('created_at')->first();

            return [
                'id' => $server->id,
                'name' => $server->name,
                'totalLaps' => (clone $laps)->count(),
                'totalPlayers' => (clone $laps)->distinct('player_id')->count('player_id'),
                'mapsPlayed' => (clone $laps)->distinct('map_id')->count('map_id'),
                'lastActiveAt' => $lastLap?->created_at,
            ];
        });

        return ServerResource::collection($servers);
    }
}
