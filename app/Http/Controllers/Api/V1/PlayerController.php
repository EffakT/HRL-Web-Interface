<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PlayerResource;
use App\Models\GlobalRanking;
use App\Models\LapTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class PlayerController extends Controller
{
    // Same bounds as the other paginated read endpoints (PERF-03 audit follow-up) — kept
    // consistent across the API rather than inventing a second pair of numbers.
    private const int DEFAULT_PER_PAGE = 50;

    private const int MAX_PER_PAGE = 100;

    /**
     * GET /api/v1/players — the Global Leaderboard (see docs/players-list.md): every player with
     * at least one real lap, ranked by Global Score. Backed by the same calculator
     * (`GlobalRanking::scores()`) the Players List page uses — see that method's own docblock for
     * the score formula and tie-break order; this endpoint only reshapes and paginates its
     * output, it doesn't recompute anything or change the shared method's shape (it has other
     * consumers — Home, ServerShow, PlayerShow — so changing its return shape isn't safe here).
     *
     * Paginated via `?page=`/`?per_page=`, the same in-memory `LengthAwarePaginator` approach
     * `MapLeaderboardController` already uses — `scores()` has no query-builder form to paginate
     * at the DB level (it computes every player's full ranking up front), the same reason that
     * endpoint isn't `Model::paginate()` either.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(self::MAX_PER_PAGE, max(1, $request->integer('per_page', self::DEFAULT_PER_PAGE)));

        $rankings = GlobalRanking::scores();

        // Real per-player last-active timestamp and total lap count, each in one query rather
        // than N+1 per player — same approach App\Livewire\Players\PlayerList::loadPlayers()
        // already uses for the equivalent website page.
        $lastActive = LapTime::query()
            ->whereHas('server')
            ->selectRaw('player_id, MAX(created_at) as last_active')
            ->groupBy('player_id')
            ->pluck('last_active', 'player_id');

        $lapCounts = LapTime::query()
            ->whereHas('server')
            ->selectRaw('player_id, COUNT(*) as laps')
            ->groupBy('player_id')
            ->pluck('laps', 'player_id');

        $entries = array_map(fn (array $player): array => [
            'id' => $player['playerId'],
            'rank' => $player['rank'],
            'name' => $player['name'],
            'score' => $player['score'],
            'records' => $player['firstPlaces'],
            'mapsPlayed' => $player['mapsPlayed'],
            'totalLaps' => (int) ($lapCounts[$player['playerId']] ?? 0),
            // selectRaw()+pluck() returns a raw DB datetime string, not an Eloquent-cast Carbon
            // instance — parsed explicitly so PlayerResource can call ?->toIso8601String() the
            // same way ServerResource does for its own lastActiveAt.
            'lastActiveAt' => isset($lastActive[$player['playerId']]) ? Carbon::parse($lastActive[$player['playerId']]) : null,
        ], $rankings);

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

        return PlayerResource::collection($paginator);
    }
}
