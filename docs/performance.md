# Performance

## Current scale

`lap_times` has ~1,942 rows across 823 players at last check. This is small — most "will this scale" questions below are genuinely open until real usage data says otherwise.

## Home page recomputation (PERF-01, 2026-07-08)

Measured directly (not guessed): one `Home::loadHighlights()` call ran **132 SQL queries and took ~2.3s** at real current scale (~1,668 laps, 823 players) — this matches a live TTFB complaint of ~1.8–2.0s. Root cause, confirmed by reading the code rather than assuming: `fastestImprovements()` and `achievements()` each independently queried the same 7-day recent-laps window (a duplicate query), and — the dominant cost — both called `GlobalRanking::forPlayer()`/`GlobalRanking::mapRank()` with no `excludeLapId` (i.e. asking for each recent lap's player's *current* rank) once per recent lap. Every one of those calls re-ran `GlobalRanking::scores()`'s full computation from scratch — a query over **every** lap in the table (not just the recent window), with `player`/`map`/`server` eager-loaded, fully re-grouped and re-ranked in PHP — just to read off one player's row from the result.

**Fixed** (`app/Livewire/Home.php`): the shared 7-day `$recentLaps` query and one `GlobalRanking::scores()` call (the "current", non-excluded ranking) now happen exactly once per `loadHighlights()` call, in `loadHighlights()` itself, and get passed down to `fastestImprovements()`/`achievements()`. Every "current rank" lookup that used to call `GlobalRanking::forPlayer()`/`mapRank()` fresh now reads from that one shared snapshot instead (a player's per-map ranks are already sitting in `scores()`'s `perMap` breakdown). The `excludeLapId`-varying ("what would the ranking have looked like without this one lap") calls can't be shared across *different* laps — each is a genuinely different computation — but the exact same (player, lap) pair was independently asked for by both `fastestImprovements()` and `achievements()` whenever a recent lap's player appears in both blocks' candidate lists, so those are now memoized per-request too (`Home::forPlayerBeforeLap()`).

**Result of the dedup pass alone**: 132 → 94 queries (-29%), ~2.3s → ~1.5s (-35%) for the same `loadHighlights()` call at the same real data volume; confirmed against the live site too. Re-audited afterward and correctly assessed as "partially improved, not resolved" — DB time was only ~170ms of the remaining ~1.5s, meaning the bulk of the cost was PHP-side hydration/ranking that per-request dedup alone couldn't remove, and every visitor loading the homepage between two lap submissions was still paying that cost independently.

**Fully closed (2026-07-08, same day)**: cached the entire `$highlights`/`$quickStats` computation as one blob (`Home::CACHE_KEY = 'home:highlights'`), since it's identical for every anonymous visitor and only changes when a new lap is submitted. Invalidated by a new `App\Listeners\InvalidateHomeHighlightsCache`, registered on the already-firing `App\Events\LapSubmitted` (auto-discovered, not queued — `ProcessNewLap` itself runs synchronously in the webhook's own HTTP request, not on a queue, so this listener already runs inline there too). A 10-minute TTL is kept as a safety net for the one case invalidation doesn't cover (the underlying data changing without a lap being submitted, e.g. archiving a server), not as the primary invalidation mechanism. Scoped to `Home` only, not `GlobalRanking` itself — `GlobalRanking::scores()` is called by other pages too (`PlayerList`, `ServerShow`, `PlayerShow`), but caching it there would be a bigger, broader change affecting a shared, already-well-tested calculator; deferred until profiling shows those pages need it too.

**Two real bugs in that caching fix, caught by code review before shipping (same day)**: (1) a cache **stampede** — a bare `Cache::remember()` has no locking, so every concurrently-connected visitor would independently rebuild at once right after any invalidation; (2) a **stale-write race** — a rebuild already in flight when a new lap invalidates the cache would, once it finished, write its now-outdated result right back and silently undo the invalidation for up to the full TTL. Fixed with a generation key (`Home::GENERATION_KEY`, bumped on invalidation instead of the data key being deleted directly — a stale in-flight rebuild started under the old generation can only ever write to an abandoned key nobody reads again) plus a `Cache::lock()` with a cache recheck on acquisition (only one request actually rebuilds; the rest wait briefly then read its result, falling back to computing directly if the wait times out). A real, non-obvious gotcha caught along the way: `Cache::increment()` on the `database` cache store (this app's real driver) returns `false` and does nothing if the key was never set — unlike some other drivers, it doesn't auto-initialize — so the generation key needs an explicit `Cache::add(..., 0, null)` before every `increment()`.

**Result**: on a cache hit, **1–2 queries and <1ms** for the computation itself (confirmed via `\DB::listen()`); the live site's real TTFB stayed at **~37–53ms** across both fixes, comfortably under the ~500ms/~20-query targets set on re-audit. A cache miss (the first request after invalidation, or after a lock-wait timeout) still costs roughly the ~94–101 queries/~1.3–1.5s measured above, but now happens once per new lap site-wide (one rebuild, thanks to the lock), not once per visitor. Real cross-test cache pollution was caught and fixed along the way: Pest's `CACHE_STORE=array` (phpunit.xml) persists for the whole test-process lifetime, not per-test, so a value one test cached would silently leak into a later test's assertions against a completely different database — fixed with a global `Cache::flush()` in `tests/Pest.php`'s `beforeEach()`. Full detail in [decisions.md](decisions.md).

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
