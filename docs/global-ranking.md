# Global Player Ranking

**Status: implemented (2026-07-06).** `App\Models\GlobalRanking` (not an Eloquent model — a stateless calculator, no table) implements this spec exactly as written below: fixed top-10 table, two-band interpolation for 11–50, full 5-level Global Score tie-break, and the scoped Server Score variant via an optional `$serverId` parameter. Consumers: Players List (`PlayerList`), Player Single (`PlayerShow`), and Server Single's Top Players (`ServerShow`). See [roadmap.md](roadmap.md) item 11 and [decisions.md](decisions.md) for implementation notes.

## Purpose

The Global Player Ranking provides an overall measure of player performance across **all** race maps — not just one map's leaderboard. It rewards consistency, broad participation, and strong-but-not-necessarily-record-setting performances, rather than a single exceptional lap on one map.

The ranking is derived entirely from race results and can be **recalculated at any time** from current data — it never depends on previously stored calculations (see "Recalculation" below, and the existing full-history-not-stored-results decision in [database.md](database.md), which this design deliberately continues).

## Principles

- Only a player's **fastest lap on each map** counts — matches the existing derived-`MIN(time)` approach already used for per-map leaderboards (see [database.md](database.md)).
- A player earns points **once per map**, from their single best lap there — never once per lap submission.
- Per-map points are based on the player's **position on that map's leaderboard**.
- Scores are **recalculated whenever leaderboard positions change** — i.e. whenever any player sets a new best lap that shifts rankings on a map.
- The algorithm is **deterministic** and does not depend on historical calculations — re-running it from scratch always produces the same result for the same underlying lap data.

## Scope: which leaderboard?

Position is taken from the **global, all-servers leaderboard for each map** (`maps.show` — see [architecture.md](architecture.md)), not the server-scoped nested leaderboard. This matches the stated purpose ("overall measure... across all race maps") — the nested per-server leaderboards are a different, narrower view and aren't part of this calculation. *(Assumption — flag if this should instead be per-server, or a blend.)*

## Ranking Points

Points are assigned per map, per player, based on leaderboard position:

| Position | Points |
|---|---|
| 1st | 100 |
| 2nd | 95 |
| 3rd | 90 |
| 4th | 86 |
| 5th | 82 |
| 6th | 79 |
| 7th | 76 |
| 8th | 73 |
| 9th | 70 |
| 10th | 68 |
| 11th–25th | Linear interpolation, 68 → 40 (steeper end of a two-stage decline — see formula below) |
| 26th–50th | Linear interpolation, ~39 → 10 (gentler decline than the 11–25 band) |
| 51st+ | 0 |

### Interpolation formula

Positions 1–10 are fixed (table above). Positions 11–50 decay linearly in two bands with different slopes — steep near the top (1–10), moderate through 11–25, gentle through 26–50 — so the curve flattens out as position number grows:

```
band 11–25:  points(rank) = 68 + (40 - 68) * (rank - 10) / (25 - 10)
band 26–50:  points(rank) = 40 + (10 - 40) * (rank - 25) / (50 - 25)
rank ≥ 51:   points = 0
```

Round to the nearest whole point. Sample values:

| Rank | Points |
|---|---|
| 11 | 66 |
| 15 | 59 |
| 20 | 49 |
| 25 | 40 |
| 26 | 39 |
| 30 | 34 |
| 40 | 22 |
| 50 | 10 |

**The exact values/curve may be adjusted over time without affecting stored race data** — this is a live parameter of the ranking algorithm, not something baked into `lap_times`. Implementation should keep this table/formula in one clearly-isolated place (e.g. a single class/config), not duplicated across queries, precisely so it can be tuned later without a migration.

## Global Score

A player's **Global Score = the sum of their per-map points across every map** they have a recorded lap on (not an average — see "Design Goals" below for why sum was chosen over average).

**A/B-able via config (added 2026-07-06)**: `config('ranking.global_score_variant')` (env `GLOBAL_RANKING_VARIANT`, default `sum`) switches between `sum` (this section, as originally spec'd) and `average` (points ÷ maps played, regularized — see below) without a code change — see [config/ranking.php](../config/ranking.php). This exists because real data surfaced a genuinely debatable case: a player with a flawless record on fewer maps can be out-scored by a player who's merely "very good" (rank 2-4) across more maps, under `sum` — see [decisions.md](decisions.md) for the concrete example. Both variants share the exact same per-map points table/tie-break; only the final aggregation differs. `average` isn't the "correct" answer, it's the alternative being compared against `sum` — no decision has been made yet on which one this project should settle on long-term.

**`average` is a weighted (Bayesian) average, not a naive one.** A naive `points ÷ maps played` has its own opposite failure mode, also found on real data: a player who's raced exactly 1 map and got rank 1 on it scores a flat 100.0 — beating a player who holds the record on 6 of 9 maps (97.0) — because a 1-map sample can't be distinguished from a 9-map sample by a raw average. Regularized using the same idea as IMDB's "weighted rating": each player's own average is blended with the overall average across all ranked players, weighted by how many maps they've actually raced (`config('ranking.average_confidence_maps')`, default **2** — think of it as "2 virtual maps of average performance" assumed by default). A player with very few maps gets pulled hard toward the middle; the effect fades out as they race more of their own maps. See [decisions.md](decisions.md) for the exact real-data before/after, including why the default is 2 and not something larger like 5 — this project's real map pool is tiny (10 maps total), so a confidence constant close to that ceiling would mean nobody, even a maximally-engaged player, ever really escapes the pull.

## Per-map calculation

For every map:
1. Determine each player's best lap time on that map (existing derived `MIN(time)` query, global scope — see [database.md](database.md)).
2. Rank players by best lap time, fastest first.
3. **Tie handling**: if two players have the *exact same* best lap time on a map, the player who **set that time earliest** takes the higher position. (Chosen for consistency with the overall tie-break rule 5 below, extended down to per-map position — not "standard competition ranking" where tied players share a position.)
4. Assign points per player using the Ranking Points table/formula above.
5. Ignore duplicate entries for a player on the same map — only their single best lap contributes; a player cannot earn points twice for one map.

## Tie-breaking (Global Score ties)

If two players have the same Global Score, resolve in order:
1. Most 1st-place finishes across all maps.
2. Most top-3 finishes across all maps.
3. Most top-10 finishes across all maps.
4. Fastest single lap time (any map).
5. Earliest achievement date (whichever player reached their current standing first).

If all five are still tied, the players are genuinely tied — no further tiebreaker is defined.

## Recalculation

This is a heavier aggregate than a single map's leaderboard — it spans every map × every player, not one map. Per the [performance.md](performance.md) precedent (derive via query at current scale, ~2k `lap_times` rows, only cache if profiling says so), the same default applies here: **compute on read, don't pre-store**, until real usage data says otherwise.

Two realistic follow-up questions once this is implemented (not decided yet):
- Whether to recompute the *entire* ranking on every new lap, or only the affected map's rankings (cheaper — a new lap only changes point allocations for players on that one map, not every map).
- Whether a cached "current global rankings" table becomes worth it at real scale — same question already open for per-map bests in [performance.md](performance.md), and the same answer likely applies: revisit only if profiling shows it's actually slow.

## Design Goals

The algorithm should:
- Reward consistent performance (broad points across many maps beats one lucky record).
- Encourage participation across multiple maps (Global Score = sum, not average — see above; playing more maps well can only help, never dilute).
- Prevent a single map from dominating the overall ranking (points per map are capped at 100, regardless of how much of a record margin the 1st-place lap has).
- Remain easy for players to understand and track their progress (fixed table for top 10, two clearly-described interpolation bands below that — no hidden curve-fitting).
- Be fully recalculable at any time without relying on historical data (matches the project's broader full-history/derived-reads philosophy — see [database.md](database.md)).

## Scoped variant: Server Score

The same points table/formula, applied to one server's **nested** leaderboard positions instead of the **global** leaderboard, summed only across maps played on that one server — used for the "Top Players" section of the Server Single page. See [server-single.md](server-single.md). Not a separate algorithm, just this one applied at a different leaderboard scope — keep the points table/interpolation logic in one shared place in the implementation so both scores can never drift apart from using two copies of the same formula.

## Consumers

Four planned pages read from this algorithm's output: [players-list.md](players-list.md) (Global Leaderboard, sorted by Global Score), [player-single.md](player-single.md) (a player's own Global Rank/Score, plus per-map Points and Map Rank — the most granular consumer, surfacing individual per-map point contributions, not just the summed total), [server-single.md](server-single.md) (Server Score, the scoped variant above), and [homepage.md](homepage.md) (Top 10/Top 3 entries and rank jumps in the Fastest Improvements highlight block). Keep the points table/formula in one implementation location precisely because this many places depend on it staying consistent.

## Open items

- Confirm the global-vs-nested leaderboard scope assumption above (implemented as specified — global scope for Global Score, server-scoped for Server Score — but never explicitly re-confirmed by the user post-implementation).
- ~~Decide the UI surface for this~~ — **resolved**: `/players` becomes the primary surface, redesigned as a Global Leaderboard sorted by Global Score. See [players-list.md](players-list.md). It also appears on `players.show` (Global Rank/Score header badges, plus per-map Points/Map Rank in the Performance by Map table) — **resolved**, both surfaces implemented.
- ~~Decide recalculation strategy (full vs. incremental) once real backend queries exist~~ — **resolved for now**: full recompute on every call (`GlobalRanking::scores()` re-derives from `lap_times` each time, no caching), per this doc's own stated default. Real-scale measurement: ~688 ranked players / ~9 maps computes in ~0.2s — revisit only if profiling at real scale says otherwise.
- The 5th Global Score tiebreaker ("earliest achievement date") is interpreted as the earliest `created_at` among a player's per-map personal-best laps — the spec doesn't define this mechanism precisely, and no case in real data has been observed to actually reach this tiebreaker level (4 tiers up, ties are already vanishingly rare).
