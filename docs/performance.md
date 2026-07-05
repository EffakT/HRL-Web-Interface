# Performance

## Current scale

`lap_times` has ~1,942 rows across 823 players at last check. This is small — most "will this scale" questions below are genuinely open until real usage data says otherwise.

## Leaderboard ranking

**Decision**: personal bests and course records are **derived reads** (`MIN(time) GROUP BY player_id, map_id, server_id`), not stored/upserted rows. A Redis sorted-set approach was considered (O(log N) updates vs. recomputing from SQL) but **not adopted** — the current scale doesn't justify the added complexity. Revisit only if the `MIN(time)` query genuinely becomes a bottleneck (unlikely at ~2k rows; worth re-measuring once real data volume is known and the query is actually implemented against real tables).

## Not yet relevant (no real queries exist yet)

Everything below is guidance for **when** real Eloquent queries get built (see [roadmap.md](roadmap.md)), not audited issues today, since the app currently runs entirely on mock arrays:

- Eager-load relationships (`with()`) to avoid N+1 on any query touching `lap_times` → `players`/`maps`/`servers`.
- Consider `Model::preventLazyLoading()` in development once models exist.
- The most-active-server query (90-day rolling window over `lap_times`, grouped by server — see [most-active-server.md](most-active-server.md)) should be indexed on `server_id` + `created_at`, and likely `player_id`/`map_id` too since it counts distinct (player, map) pairs — check this when writing the migration/query.
- Cache the derived "current bests" query if profiling ever shows it's hot — but don't pre-build a cache layer speculatively.

## Global Player Ranking

Heavier than a single map's leaderboard query — spans every map × every player, not one map+server pair. Same default as above applies: **compute on read, don't pre-store**, until real usage data says otherwise. Full spec and the specific open questions (full vs. incremental recalculation on each new lap) are in [global-ranking.md](global-ranking.md).

## Most Active Server

Unlike the other derived reads on this page, this one is explicitly spec'd as "recalculated periodically" rather than computed live per page load (see [most-active-server.md](most-active-server.md)) — a 90-day rolling window aggregate (counting distinct player/map participations, not raw lap rows) is a heavier query than a simple `MIN(time)` lookup, so periodic recalculation (interval not yet decided) plus a cached result is the planned approach from the start here, not a "wait and see if it's slow" case like the others on this page.

## Server Single page

- **All Laps table** ([server-single.md](server-single.md)) is a full, unfiltered history table for one server — given laps are never pruned ([database.md](database.md)), this **must paginate from the start**, not as a later optimization. A popular server could accumulate thousands of rows.
- **Top Players (Server Score)** reuses the Global Player Ranking computation ([global-ranking.md](global-ranking.md)) scoped to one server's nested leaderboards — same cost profile as Global Ranking, just bounded to one server's maps instead of every map, so meaningfully cheaper per-computation, but still a derived aggregate, not a simple lookup.
- **"Number of records set"** — cost depends entirely on which of the two definitions in [server-single.md](server-single.md) is chosen: "current records held" is a cheap comparison against existing leaderboard state; "historical record-breaking events" requires a point-in-time replay of lap history and would be meaningfully more expensive. Worth factoring into that still-open decision.

## Players List page

- This page renders the **entire** Global Leaderboard ([players-list.md](players-list.md)), sorted by Global Score — likely the single most expensive page in the app once real data is wired up, since it needs every player's Global Score computed (itself a per-player aggregate across every map) just to sort and paginate the list, not just to display one player's score on demand. Pagination is a given; whether the *whole* sorted list needs computing per request or can be incrementally maintained is a real open question once this is built — don't assume the same "just derive it on read" default is automatically fine here at real scale, the way it plausibly is for a single leaderboard or a single player's score.
- **Trending indicator**: if implemented via periodic snapshots (see [players-list.md](players-list.md) option 1), that's a new scheduled-job + storage mechanism, not a query optimization question — factor that into whichever Trend implementation gets chosen.

## Player Single page

- **Performance by Map** ([player-single.md](player-single.md)) computes Map Rank + Points for every map the player has raced — cheaper than the Players List page's whole-leaderboard problem (bounded by one player's map count, not every player), but still one Global-Ranking-shaped computation per map row, not a single query.
- **Recent Laps** is explicitly a bounded recent feed, not full history — no pagination-from-day-one concern the way [server-single.md](server-single.md)'s "All Laps" has, precisely because it's spec'd as limited.

## Real-time updates

Planned: Laravel Reverb + Echo, broadcasting a `LeaderboardUpdated` event (map+server scoped channel) only when a new lap is a PB/record — not on every lap submission, to avoid flooding scoped channels. Broadcast events should be dispatched via the queue (Redis), not synchronously, so the webhook response isn't blocked.
