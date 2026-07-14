<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ServerResource;
use App\Models\LapTime;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServerController extends Controller
{
    // Same bounds as MapController/MapLeaderboardController (PERF-03 audit follow-up) — kept
    // consistent across the read API rather than inventing a second pair of numbers.
    private const int DEFAULT_PER_PAGE = 50;

    private const int MAX_PER_PAGE = 100;

    /**
     * GET /api/v1/servers — every active (non-archived) server with real, derived stats,
     * paginated. Real scale today is a handful of servers — this was deliberately unpaginated
     * until now — but paginating up front costs nothing for a small result set and avoids ever
     * needing a breaking response-shape change later if the server count genuinely grows.
     * Real DB-level pagination (`Server::paginate()`), same as `MapController` — a plain per-row
     * listing, not the full-ranking computation `MapLeaderboardController` needs.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(self::MAX_PER_PAGE, max(1, $request->integer('per_page', self::DEFAULT_PER_PAGE)));

        $servers = Server::orderBy('id')->paginate($perPage)->through(function (Server $server): array {
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
