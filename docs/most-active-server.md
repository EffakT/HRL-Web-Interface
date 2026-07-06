# Most Active Server Algorithm

**Status: implemented for real (2026-07-06)**, except the scheduled recalculation job (see "Recalculation cadence" below — deliberately not built, live queries are fast enough at real scale). `App\Models\MostActiveServer` implements the Activity Score, recency bonus, tie-break, and display-only 30d/90d unique-player pair exactly as specced below. Wired into `ServerList`'s featured card and Homepage's Most Active Server highlight. See [decisions.md](decisions.md) for real-data verification and [roadmap.md](roadmap.md) for sequencing.

**Supersedes** the earlier placeholder definition in [database.md](database.md) ("ranked by `lap_times` row count in a rolling 30-min window, tie-broken by most recent lap") — that was a rough stand-in written before this full spec existed. This doc is now the source of truth for "most active server."

## Purpose

Highlights servers with the highest current *and* overall player engagement — surfaced as the "MOST ACTIVE" featured server card on the Servers page (see [architecture.md](architecture.md)). The score is derived entirely from race data and recalculated periodically (see "Recalculation cadence" below) — not stored as a running counter that drifts from source data.

## Activity Score

```
Activity Score = (Unique Players × 10) + (Valid Laps × 1) + (Maps Played × 20)
```

All three metrics are computed over a **rolling 90-day window**, not all-time — this is a deliberate resolution to a tension in the original spec: an all-time cumulative count can never meaningfully shrink, which would contradict the "allow inactive servers to fall off over time" design goal below. A 90-day window means a server that goes quiet actually does fade out of the ranking as its activity ages past 90 days, and can climb back if it becomes active again — satisfying that goal for real, not just via the recency bonus.

### Definitions (within the 90-day window)

- **Unique Players** = number of distinct players who have recorded at least one lap on this server.
- **Valid Laps** = number of distinct **(player, map)** pairs with at least one lap on this server — i.e. *participations*, not raw lap submissions. **This is a deliberate change from a literal "count every lap row"** reading of the original spec: counting raw laps would let one player inflate the score unboundedly by grinding the same map repeatedly, directly contradicting the "prevent one player from dominating" design goal below. Counting distinct (player, map) pairs instead means repeat laps on a map a player has already played add nothing further — matches the same "count participation, not volume" principle already used in [global-ranking.md](global-ranking.md) (best lap per map, not every lap).
- **Maps Played** = number of distinct maps with at least one lap on this server.

Note this means `Valid Laps ≤ Unique Players × Maps Played` always holds — it's bounded, not an open-ended raw count.

## Recency Bonus

Applied on top of the base Activity Score, based on the server's most recent lap:

| Most recent lap within | Bonus |
|---|---|
| 7 days | +100 |
| 30 days | +50 |
| 90 days | +20 |
| Older / none | +0 |

Only the highest applicable bonus applies (not stacked).

**Known overlap to be aware of**: since the base score's own window is also 90 days, *any* server with a nonzero base score necessarily has at least one lap within the last 90 days — meaning the "+20 within 90 days" tier will apply to essentially every server that has any measurable activity at all. In practice this tier acts as a flat "any activity" floor bonus rather than real differentiation; the 7-day and 30-day tiers are where the actual recency signal lives. Not necessarily wrong, but worth knowing before tuning these numbers later.

## Tie-breaking

If two servers have the same total score (Activity Score + Recency Bonus):
1. More unique players.
2. More maps played.
3. More valid laps (participations, per the definition above).
4. Most recent activity (latest lap timestamp).

## Lap validity — deferred

"Valid Laps" assumes some notion of a lap being legitimate vs. not, but the real schema (`lap_times`) has **no validity/flag column**, and no anti-cheat mechanism exists today. **Explicitly deferred as a future problem** — one direction being considered is a checksum on the map file to detect modded maps, though that wouldn't catch other cheating vectors (scripts handling hog-jumps, portals, etc.). Until a real validity mechanism exists, **every recorded lap is treated as valid** for this algorithm — "Valid Laps" in the formula above should be read as just "Laps" for now. Revisit this doc once a validity/anti-cheat design exists.

## Recalculation cadence

Spec says "recalculated periodically" without a specific interval. **Resolved for now (2026-07-06)**: no scheduled job — `MostActiveServer::scores()` computes live on every call, same "derive, don't cache" precedent as `GlobalRanking`. At real current scale (3 active servers, a handful of laps per week) this is trivially fast; a scheduled job is only worth building once profiling at real scale says a live query is actually too slow (see [performance.md](performance.md)).

## Design Goals

The algorithm should:
- Reward active communities rather than idle servers.
- Encourage a variety of maps (the ×20 per-map weight is the highest of the three terms, deliberately).
- Prevent one player repeatedly racing from dominating the score — satisfied by counting distinct (player, map) participations rather than raw lap volume (see above). Note this doesn't prevent one very dedicated player from contributing meaningfully by playing *many different* maps — that's intended, not a loophole; "dominating via repetition" is what's prevented, not "a single active player counts for something."
- Allow inactive servers to fall off the list over time, while still allowing a server to regain its position if it becomes active again — satisfied by the 90-day rolling window on the base score (not an all-time cumulative total), with the recency bonus adding extra weight to very recent activity on top.

## Open items

- ~~Exact recalculation interval~~ — **resolved**: no scheduled job, computed live (see above).
- Lap validity / anti-cheat mechanism (see above) — tracked as a future problem, not blocking this spec.
- Whether the 90-day base window and the 90-day recency-bonus tier should actually be different lengths, given the overlap noted above.
- ~~UI surface: currently just the single featured "MOST ACTIVE" server card on `/servers`~~ — **done**: `ServerList`'s featured card and Homepage's top-3 podium-style highlight are both wired to `App\Models\MostActiveServer`.
- ~~The homepage's Most Active Server block also wants 30-day and 90-day unique-player counts shown side by side~~ — **done**, both computed and displayed alongside the score's own 90-day base window.
