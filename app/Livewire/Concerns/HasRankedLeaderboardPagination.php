<?php

namespace App\Livewire\Concerns;

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;

/** Shared pagination for ranks 4+; the top-three podium remains visible on every page. */
trait HasRankedLeaderboardPagination
{
    use WithPagination;

    // 10 per page, sub the podium, leaves 7 for the rest of the leaderboard
    private const PLAYERS_PER_PAGE = 7;

    /**
     * `$pageName` lets a component run this alongside its own separately-paginated section
     * (e.g. ServerShow already paginates Latest Laps under the default 'page' name) without the
     * two colliding — pass a distinct name and use the matching `previousPage($pageName)` /
     * `nextPage($pageName)` calls in the view.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function rankedPlayers(string $pageName = 'page'): LengthAwarePaginator
    {
        $rankedPlayers = array_slice($this->players, 3, null, true);

        // Clamping to the real last page (not just flooring at 1) matters here the same way it
        // does for the API's equivalent pagination (docs/api.md) — without it, an extreme `page`
        // value survives Livewire's own `(int)` cast (which clamps to PHP_INT_MAX rather than
        // erroring) and `($currentPage - 1) * self::PLAYERS_PER_PAGE` overflows into a float,
        // which array_slice() rejects with a TypeError instead of a page 1 result.
        $lastPage = max(1, (int) ceil(count($rankedPlayers) / self::PLAYERS_PER_PAGE));
        $currentPage = min($lastPage, max(1, (int) $this->getPage($pageName)));

        return new LengthAwarePaginator(
            array_slice(
                $rankedPlayers,
                ($currentPage - 1) * self::PLAYERS_PER_PAGE,
                self::PLAYERS_PER_PAGE,
                true,
            ),
            count($rankedPlayers),
            self::PLAYERS_PER_PAGE,
            $currentPage,
            [
                'path' => request()->url(),
                'query' => request()->query(),
                'pageName' => $pageName,
            ],
        );
    }
}
