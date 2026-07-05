# Global Player Ranking

**Status: planned, not yet implemented.** This doc is the spec — no `GlobalRanking` code, table, or route exists yet. See [roadmap.md](roadmap.md) for where this sits relative to other planned work.

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

- Confirm the global-vs-nested leaderboard scope assumption above.
- ~~Decide the UI surface for this~~ — **resolved**: `/players` becomes the primary surface, redesigned as a Global Leaderboard sorted by Global Score. See [players-list.md](players-list.md). Whether it also appears on `players.show` (the individual player page) is still open.
- Decide recalculation strategy (full vs. incremental) once real backend queries exist.
