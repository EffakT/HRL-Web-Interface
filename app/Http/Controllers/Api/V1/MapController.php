<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\MapResource;
use App\Models\LapTime;
use App\Models\Map;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MapController extends Controller
{
    // Same bounds as MapLeaderboardController (PERF-03 audit follow-up) — kept consistent across
    // the read API rather than inventing a second pair of numbers.
    private const int DEFAULT_PER_PAGE = 50;

    private const int MAX_PER_PAGE = 100;

    /**
     * GET /api/v1/maps — every map, paginated with real, derived stats. Unlike GET
     * /api/v1/servers (a handful of rows, deliberately unpaginated), the number of Map rows
     * genuinely grows over time — a checkpoint-count mismatch or race_type variant each forks its
     * own `{name}-splits-{count}`/`-anyorder`/`-rally` row (see docs/security.md,
     * docs/decisions.md) — so this uses real DB-level pagination (`Map::paginate()`), not the
     * in-memory rank-then-slice `MapLeaderboardController` needs for its own full-ranking
     * computation.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(self::MAX_PER_PAGE, max(1, $request->integer('per_page', self::DEFAULT_PER_PAGE)));

        $maps = Map::orderBy('id')->paginate($perPage)->through(function (Map $map): array {
            return [
                'id' => $map->id,
                'name' => $map->name,
                'label' => $map->label,
                'checkpoint_count' => $map->checkpoint_count,
                'total_laps' => LapTime::where('map_id', $map->id)->count(),
            ];
        });

        return MapResource::collection($maps);
    }
}
