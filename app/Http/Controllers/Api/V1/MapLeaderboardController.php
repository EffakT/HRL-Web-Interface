<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\MapLeaderboardEntryResource;
use App\Models\GlobalRanking;
use App\Models\Map;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MapLeaderboardController extends Controller
{
    /**
     * GET /api/v1/maps/{map}/leaderboard — the global (all-servers) leaderboard for one map,
     * every player's best lap, ranked. Pass `?server={id}` for that server's nested leaderboard
     * instead (see docs/architecture.md's global-vs-nested split).
     */
    public function show(Request $request, Map $map): AnonymousResourceCollection
    {
        $serverId = $request->integer('server') ?: null;

        return MapLeaderboardEntryResource::collection(
            GlobalRanking::mapLeaderboard($map->id, $serverId)
        );
    }
}
