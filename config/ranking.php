<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Global/Server Score variant
    |--------------------------------------------------------------------------
    |
    | App\Models\GlobalRanking supports two ways of turning a player's per-map
    | Ranking Points into their Global/Server Score, switchable here without a
    | code change so the two can be A/B compared against real data:
    |
    | - "sum": points summed across every map raced. The original spec —
    |   rewards breadth of participation; playing more maps well can only
    |   help, never dilute (see docs/global-ranking.md).
    | - "average": points averaged across maps raced. Rewards consistency
    |   relative to how much a player has actually played, so a flawless
    |   record on fewer maps isn't out-scored by breadth alone.
    |
    | See docs/decisions.md for the real-data example that prompted this.
    |
    */

    'global_score_variant' => env('GLOBAL_RANKING_VARIANT', 'sum'),

    /*
    |--------------------------------------------------------------------------
    | Average-variant confidence constant
    |--------------------------------------------------------------------------
    |
    | A naive average (points ÷ maps played) lets a player with a single perfect
    | map trivially outscore broad excellence (1 map at rank 1 = 100.0 vs. a
    | record holder on 6 of 9 maps at 97 — see docs/decisions.md). "average"
    | is regularized with a Bayesian/weighted average — the same idea as
    | IMDB's weighted rating — so a low-sample average gets pulled toward the
    | overall mean until a player has raced roughly this many maps, at which
    | point their own average dominates. Higher = more skeptical of small
    | samples; lower = trusts a player's own average sooner.
    |
    | Keep this small relative to the real map count (10 maps total as of
    | 2026-07-06) — a value close to or above the total map count means even a
    | player who's raced every map never escapes a meaningful pull toward the
    | overall mean, which defeats the point (see docs/decisions.md for the
    | real-data example: confidenceMaps=5 permanently capped a 9-map, 6-record
    | player at 64% weight on their own average).
    |
    */

    'average_confidence_maps' => env('GLOBAL_RANKING_AVERAGE_CONFIDENCE_MAPS', 2),

];
