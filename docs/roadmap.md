# Roadmap

## Done

- [x] Dev environment set up: real prod DB imported into MySQL `redesign_hrl`, all 16 tables confirmed matching `old_schema.md`.
- [x] Laravel Boost installed, MCP server + skills working.
- [x] Actual schema inspected and reconciled against the original (pre-inspection) data model plan.
- [x] History-retention approach decided: full `lap_times` history, kept forever.
- [x] Claim-code ownership system: decided deferred, not a priority.
- [x] Tailwind theme + design tokens set up from the HUD styleguide comps.
- [x] Shared Blade layout + mobile nav overlay built.
- [x] All frontend pages built and routed on mock data: Home, Servers (list + single + nested leaderboard), Maps (list + global leaderboard), Players (list + single + lap popup), Opt-In, Contact.
- [x] Lap Detail modal/popup with split comparison, shared across leaderboards and the player page via `HasLapDetailModal`.
- [x] Variable split-count support (podium sparkline + scrollable modal), tested up to 14 sectors.
- [x] Cache-table incident fixed (Livewire click fatal error).
- [x] Full documentation suite (this file and its siblings) established.
- [x] Test/analysis tooling in place: Pest (first real route test), PHPStan/Larastan, Rector, Semgrep config (not installed — see [testing.md](testing.md)).

## Next up (in rough order)

**Re-prioritized (2026-07-05): frontend design comes first, all real algorithms/data stay static/mock until the design phase is done.** All four specs below (Server Single, Players List, Player Single, Homepage) reference Global Player Ranking, Most Active Server scoring, and historical record-events — but none of that needs to be *real* to build and review the UI. Every page built so far in this project used exactly this sequencing (mock data first, wire real queries later), and there's no reason for these four to be the exception just because their mock numbers happen to be described by a spec doc instead of being invented ad hoc.

### Phase 1 — Frontend design (static/mock data)

1. ~~**Build out the Server Single page additions**~~ — **done.** Spec: [server-single.md](server-single.md). Stats Card, Top Players (Server Score, podium-styled), and a real paginated All Laps table (`LengthAwarePaginator` over 47 mock rows, Livewire's `WithPagination` for page state) all added to `ServerShow`/`server-show.blade.php`. "Top 3 Fastest Laps" was built, then removed after review — comparing raw lap times across maps of very different lengths didn't produce a meaningful ranking. Mock values used throughout for Server Score/records/averages — none of the underlying algorithms are real yet. See [decisions.md](decisions.md).
2. ~~**Redesign the Players List page as a Global Leaderboard**~~ — **done.** Spec: [players-list.md](players-list.md). Info card (4 stats, Server Single badge style — no header, directly below the `<h1>`), top-3 podium (shared `podium.blade.php`), 7-column table (Position/Player/Score/Records/Maps/Active/Trend) for rank 4+. Trend indicator built as a new shared partial (`resources/views/livewire/partials/trend-indicator.blade.php`, ▲/▼/– ) with mocked direction/delta values — the indicator's real *mechanism* (see Open Questions) is still undecided, only its mocked appearance was needed here. See [decisions.md](decisions.md).
3. ~~**Expand the Player Single page**~~ — **done, completing Phase 1.** Spec: [player-single.md](player-single.md). Player Info header (Global Rank/Score badges), Stats Card, Best Performance (built as curated achievements, not raw top-3 laps — same cross-map-time reasoning that removed Server Single's equivalent section, see [decisions.md](decisions.md)), Performance by Map (existing lap table extended with Map Rank/Points columns), Fav Servers, and Recent Laps (reuses the Performance-by-Map array for now — see Open Items). All builds around the pre-existing working Lap Detail popup, unchanged.
4. ~~**Redesign the Homepage**~~ — **done.** `/` is now `App\Livewire\Home`, spec: [homepage.md](homepage.md). Section order: Quick Stats, then Latest Highlights (6 candidate blocks as partials under `resources/views/livewire/partials/highlights/`, driven by the real fixed-priority selection logic against mock "current state" data — one block mocked empty to prove the fallback path works), then Quick Links, then the old site's Future Plans / Known Issues / Changelog content (kept, moved to the bottom of the page rather than dropped — `welcome.blade.php` deleted as redundant). See [decisions.md](decisions.md) for detail.
5. ~~**Podium partial extraction**~~ — **done**, per explicit user feedback while building Server Single ("the top 3 fastest should use the podium style, always... follow the structure as per the map single"). `resources/views/livewire/partials/podium.blade.php` is the generic version (no click/sparkline). Used by Server Single's Top Players section and Players List's top-3 (its second real consumer). Homepage's Most Active Server block deliberately stays on its own compact list style — confirmed by the user, not a future candidate. See [coding-standards.md](coding-standards.md).
6. ~~Extend `tests/Feature/RoutesTest.php` for each new/changed route as it's built~~ — **not needed**: none of Phase 1's four page specs introduced new routes, only new content on existing ones (`/`, `/servers/{id}`, `/players`, `/players/{id}`), and `RoutesTest.php` already asserts all of them render successfully. Revisit this item once Phase 2 adds genuinely new routes (e.g. real API endpoints).

**Phase 1 complete** (2026-07-05) — all four page specs (Server Single, Players List, Player Single, Homepage) are built on mock data. Phase 2 (backend/real data) is next.

### Phase 2 — Backend / real data

7. **Inspect the old repo's webhook/job code** — deferred pending access. Needed before rebuilding the submission pipeline; confirm payload shape and what's portable vs. rewrite-from-scratch.
8. **Wire real backend data** — swap mock arrays in every Livewire component for real Eloquent queries. **In progress**: `ServerList` (Servers List), `ServerShow` (Server Single), `ServerMapLeaderboard` (nested per-server map leaderboard), and `MapList` (Maps List) are wired for real. `ServerList`: real server names/laps/distinct-player counts; `region`, live online status, player capacity, and ping had no real-schema equivalent and were dropped or replaced with an honest derived proxy rather than fabricated (see [decisions.md](decisions.md)) — "online" means "lap within the last 24h," "now playing" is the most recent lap's map, "most active" is a simple real 30-day-lap-count proxy, **not** the full Activity Score algorithm (still item 12 below). `ServerShow`: Maps section, Stats Card, and Latest Laps (paginated) all query `lap_times`/`servers`/`maps` directly; Top Players (Server Score) stays mock, blocked on item 11. `ServerMapLeaderboard`: full ranked leaderboard (one row per player's best lap on this server+map, real tie-breaking, real gap-to-leader), plus **real per-checkpoint split comparison** for both leaderboard pages via the shared `LapTimeSplit::compare()` helper. `MapList`: maps with lap history are listed alphabetically by public label with global all-server lap counts and best laps; zero-lap maps are hidden because they have no leaderboard to visit. Remaining components (`MapLeaderboard`, `PlayerList`, `PlayerShow`, `Home`) are still mock — see [decisions.md](decisions.md).
9. ~~**Write migrations**~~ — **done, for a different reason than expected.** No columns needed adding for real reads. What *was* needed: `create_*_table` migrations for every real table (`servers`, `maps`, `players`, `lap_times`, `lap_time_splits`, `servers_maps`, `players_servers`, `users_players`, `users_servers`), replicating the real schema exactly — these never run against the real dev/prod DB (tables already exist there) but are required so a fresh environment (the test suite's in-memory SQLite, CI, a new dev machine) has a schema to build factories/tests against at all. This was discovered as a blocker while wiring `ServerShow` to real data — see [decisions.md](decisions.md). Also includes `widen_lap_times_timestamps_to_datetime`, which widened `lap_times.created_at`/`updated_at` from `DATE` to `TIMESTAMP` in the real DB so future lap submissions capture real time-of-day.
10. ~~**Build Eloquent models + relationships.**~~ — **done.** `Server`, `Map`, `Player`, `LapTime`, `LapTimeSplit`, `PlayerClaim`, `ServerClaim` under `app/Models/`, matching factories, all relationships verified against the real `redesign_hrl` data (817 real players, 1657 real laps). See [database.md](database.md)'s new "Eloquent models" section.
11. **Implement Global Player Ranking for real** — spec: [global-ranking.md](global-ranking.md). Replaces the Phase 1 mock Global Score/Rank/Points values across Players List, Player Single, and Server Single (Server Score variant).
12. **Implement Most Active Server scoring for real** — spec: [most-active-server.md](most-active-server.md). Needs a scheduled recalculation job (interval not yet decided), not just a live query.
13. **Implement the historical record-breaking-events derivation** — needed for Homepage's "Latest / Current Records" block and the "number of records set" stats elsewhere (see Open Questions) — this is real query/algorithm work, not just a definition choice at this point.
14. **Rebuild the webhook → job → broadcast flow** (see [database.md](database.md) for the planned shape).
15. **Build the new API endpoints** (see [api.md](api.md)).
16. **Wire Reverb/Echo** for live leaderboard updates.
17. **Build out real Pest coverage** beyond route-renders — component behavior, the ranking/scoring algorithms, webhook/job pipeline (see [testing.md](testing.md)).
18. ~~**Wire real per-checkpoint split comparison for the Lap Detail modal**~~ — **done, on Server Single and the nested Server Map Leaderboard.** Both override `HasLapDetailModal`'s mock `getComparisonProperty()` with real `LapTimeSplit` data via the shared `LapTimeSplit::compare()` helper. `PlayerShow` and the global `MapLeaderboard` still use the mock fallback — apply the same override pattern to each once those pages get wired to real lap data. See [decisions.md](decisions.md).

## Open questions / not yet decided

- Confirm the dev DB user actually has read-only or otherwise scoped privileges, as originally intended.
- Exact webhook payload shape from the game server.
- How "current map" per server gets updated — no `current_map_id`-equivalent column exists; either derive it from the most recent `lap_times` row per server, or add a column if a live signal exists from the game server.
- Whether a cached "current bests" table is ever needed, or whether deriving via `MIN(time)` SQL stays sufficient — revisit once real query performance is measurable (~2k rows currently, likely fine).
- Final API response shapes for the 3 planned endpoints.
- Deployment/cutover plan specifics: staging environment details, DNS/cutover timing.
- Auth strategy (`/login`, `/register`) — not designed yet.
- `/players/{id}`'s exact old-site semantics (`"Leaderboard for {Player Name}"`) vs. the new "personal lap log" framing — currently intentionally different, revisit if that's a problem.
- **Run Semgrep for real** — couldn't install it in this sandbox (no pip/Homebrew/Docker). Needs running somewhere that has one of those (CI, or a local dev machine) to validate the custom `.semgrep.yml` rules actually work as intended, and to get real findings against the codebase for the first time. See [testing.md](testing.md).
- **Decide whether/when to apply the pending Rector fixes** — `composer rector-dry` currently proposes 16 files' worth of changes (mostly `declare(strict_types=1)`), deliberately not applied yet.
- **Lap validity / anti-cheat mechanism** — needed for the Most Active Server score's "Valid Laps" term to mean anything beyond "any recorded lap." A map-file checksum (to detect modded maps) is one idea being considered, but wouldn't catch script-based cheats (hog-jumps, portals, etc.). See [most-active-server.md](most-active-server.md) — explicitly parked as a future problem, not blocking that spec.
- **Most Active Server recalculation interval** — spec says "periodically," exact cadence not decided (see [most-active-server.md](most-active-server.md)).
- **"Average lap time" definition** (Server Single Top Players) — raw average across all attempts vs. average of per-map bests. See [server-single.md](server-single.md).
- **"Number of records set" definition — now effectively forced toward one answer**: the Players List page's framing hinted at the historical reading; [homepage.md](homepage.md)'s "Latest / Current Records" highlight block goes further and **can only be built on the historical reading at all** (it needs discrete recent record-setting events, e.g. "1h ago," which the current-state reading can't produce). Still technically "not formally decided" since no one's said the words, but there's no longer a real current-state-only path that satisfies every planned feature — see [homepage.md](homepage.md), [players-list.md](players-list.md). No longer a Server Single concern — that page's own "records set" stat was dropped entirely, see [server-single.md](server-single.md)'s "Stats Card" section.
- **Trending indicator mechanism** (Players List) — periodic rank/score snapshots vs. a recent-activity proxy. See [players-list.md](players-list.md).
- **"Best Performance" definition** (Player Single) — raw top-3 laps vs. a curated "biggest achievements" list. See [player-single.md](player-single.md).
- **"Fav[orite] Servers" sort order** (Player Single) — assumed to be by lap count, not explicitly confirmed. See [player-single.md](player-single.md).
- **Per-block recency window for Homepage highlights** — defaulted to 7 days across all recency-based blocks as a starting assumption, not confirmed block-by-block. See [homepage.md](homepage.md).

## Explicitly deferred (not on the current path)

- Clans/tags (see [scope.md](scope.md)).
- Multi-tenancy.
- `/docs` API documentation page port.
- Any feature work assuming the claim-code ownership system is active.

## Ported from the old site's own "Future Plans" (for later prioritization, not started)

- Players deleting their own laps.
- Server admin lap deletion.
- Halo Custom Edition support.
- Enhanced lag detection (ping stability measures).
- Record-break email notifications, opt-in per server/map.
- Progressive Web App conversion.
