<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ResolveSubmittingIp;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\MapLeaderboardEntryResource;
use App\Models\GlobalRanking;
use App\Models\Map;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class MapLeaderboardController extends Controller
{
    // PERF-03 audit follow-up (docs/performance.md) — same order of magnitude as the read API's
    // 60/min rate limit; a few hundred players per map (per docs/api.md) fits in 2-4 pages at
    // the default.
    private const int DEFAULT_PER_PAGE = 50;

    private const int MAX_PER_PAGE = 100;

    /**
     * GET /api/v1/maps/{map}/leaderboard — the global (all-servers) leaderboard for one map,
     * every player's best lap, ranked. Pass `?server={id}` for that server's nested leaderboard
     * instead (see docs/architecture.md's global-vs-nested split), or `?port={port}` if the
     * requester IS that game server and doesn't know its own `servers.id` — same identification
     * LapSubmissionController already uses (the request's own IP, resolved through
     * `ResolveSubmittingIp` for known NAT rewrites, plus the port a game server knows about
     * itself), so a Lua client can ask "my own nested leaderboard" without a prior lookup call.
     * Paginated via `?page=`/`?per_page=` (PERF-03 audit follow-up) — see docs/performance.md for
     * why this only bounds response size, not the underlying computation:
     * `GlobalRanking::mapLeaderboard()` still ranks every qualifying lap before this slices out
     * one page, the same in-memory `LengthAwarePaginator` approach
     * `HasRankedLeaderboardPagination` already uses for the equivalent Livewire leaderboards.
     */
    public function show(Request $request, Map $map): AnonymousResourceCollection
    {
        $serverId = $this->resolveServerId($request);
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

    /**
     * `?port={port}` takes precedence over `?server={id}` when both are present — a value the
     * requester claims about *itself* (its own port) is a stronger signal than an arbitrary id it
     * might have gotten wrong. Resolves the exact same way `LapSubmissionController` identifies a
     * submitting game server: the request's real IP (`ResolveSubmittingIp`-rewritten, same as the
     * `webhook` rate limiter and lap-submission verification already do, so a NAT'd server
     * resolves to the same identity here as when it submits laps) plus the port it reports about
     * itself. `port` is cast to string before comparing since `servers.port` is stored as a
     * string (see `ProcessNewLap::resolveServer()`). Aborts with 404 rather than silently falling
     * back to the unscoped global leaderboard — a claimed ip:port with no matching `Server` row
     * (wrong port, or a server that's never yet submitted a lap) is a real misconfiguration worth
     * surfacing, not something to mask as "no server filter."
     */
    private function resolveServerId(Request $request): ?int
    {
        if (! $request->filled('port')) {
            return $request->integer('server') ?: null;
        }

        $ip = ResolveSubmittingIp::resolve($request->ip() ?? '');
        $port = (string) $request->integer('port');

        $server = Server::where(['ip' => $ip, 'port' => $port])->first();

        abort_if($server === null, 404, "No registered server found for {$ip}:{$port}.");

        return $server->id;
    }
}
