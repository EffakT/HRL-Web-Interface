<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\MapLeaderboardEntryResource;
use App\Models\GlobalRanking;
use App\Models\Map;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class MapLeaderboardController extends Controller
{
    // PERF-03 audit follow-up (docs/performance.md) — same order of magnitude as the read API's
    // 60/min rate limit; a few hundred players per map (per docs/api.md) fits in 2-4 pages at
    // the default.
    private const DEFAULT_PER_PAGE = 50;

    private const MAX_PER_PAGE = 100;

    /**
     * GET /api/v1/maps/{map}/leaderboard — the global (all-servers) leaderboard for one map,
     * every player's best lap, ranked. Pass `?server={id}` for that server's nested leaderboard
     * instead (see docs/architecture.md's global-vs-nested split). Paginated via `?page=`/
     * `?per_page=` (PERF-03 audit follow-up) — see docs/performance.md for why this only bounds
     * response size, not the underlying computation: `GlobalRanking::mapLeaderboard()` still
     * ranks every qualifying lap before this slices out one page, the same in-memory
     * `LengthAwarePaginator` approach `HasRankedLeaderboardPagination` already uses for the
     * equivalent Livewire leaderboards.
     */
    public function show(Request $request, Map $map): AnonymousResourceCollection
    {
        $serverId = $request->integer('server') ?: null;
        $perPage = min(self::MAX_PER_PAGE, max(1, $request->integer('per_page', self::DEFAULT_PER_PAGE)));

        $entries = GlobalRanking::mapLeaderboard($map->id, $serverId);

        // Clamped to the real last page (rather than only floored at 1) so an out-of-range
        // ?page= — including a value large enough that (currentPage - 1) * perPage overflows
        // PHP's int range and silently becomes a float, which array_slice() rejects with a
        // TypeError/500 — degrades to a normal empty-or-last page instead of a server error.
        $lastPage = max(1, (int) ceil(count($entries) / $perPage));
        $currentPage = min($lastPage, max(1, $request->integer('page', 1)));

        $paginator = new LengthAwarePaginator(
            array_slice($entries, ($currentPage - 1) * $perPage, $perPage),
            count($entries),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return MapLeaderboardEntryResource::collection($paginator);
    }
}
