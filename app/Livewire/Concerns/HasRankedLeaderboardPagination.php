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
        $currentPage = max(1, (int) $this->getPage($pageName));

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
