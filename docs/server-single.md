# Server Single Page

The page listing all maps played on one server (`/servers/{serverId}`, `App\Livewire\Servers\ServerShow` — see [architecture.md](architecture.md)). **Status: built, fully wired to real data** — Stats Card, Maps, Latest Laps, and Top Players (Server Score) all query the live `redesign_hrl` DB. See [decisions.md](decisions.md).

## Current state

`ServerShow` already renders a **Maps** list: map name, laps, best lap on that server, linking to the nested leaderboard (`servers.maps.show`). No change planned here beyond what's specced below.

## Planned additions

### Stats Card

**Split into 6 badges** (revised from the original 3-badge design, per explicit feedback — see [decisions.md](decisions.md)): all-time totals plus 30d/90d activity windows, for both laps and players, giving activity context rather than one flat all-time number each:

- **Laps**: all-time, last 30 days, last 90 days — counts of `lap_times` rows for this server.
- **Players**: all-time distinct players, active in last 30 days, active in last 90 days — distinct `player_id` counts, derived from `lap_times` directly (not the `players_servers` pivot, which has duplicate rows — see [database.md](database.md)).

**"Number of records set" was dropped entirely**, not just deferred — per explicit feedback, this is a player-level stat (see [players-list.md](players-list.md), [player-single.md](player-single.md)), not a server one. The historical-vs-current-state ambiguity that used to live here no longer applies to this page.

### Top Players (server-scoped ranking)

**Implemented (2026-07-06).** Top 3 players **by performance on this server**, reusing the [global-ranking.md](global-ranking.md) Ranking Points algorithm — same points table/interpolation formula — but scoped to this server's own **nested leaderboards** instead of the global (all-servers) leaderboard, via `GlobalRanking::scores($serverId)`. Same per-map points table, same "best lap per map only, once per map" rule, just computed against `/servers/{id}/maps/{id}` positions instead of `/maps/{id}` positions, and summed only across maps played *on this server* rather than across every map globally.

Call this a player's **Server Score** to distinguish it from **Global Score** (see [glossary.md](glossary.md)) — same formula, different leaderboard scope.

For each of the top 3, also show:
- **Total laps** — raw count of `lap_times` rows for that player on this server (a plain factual stat, deliberately *not* deduplicated the way [most-active-server.md](most-active-server.md)'s "Valid Laps" is — that dedup exists to prevent gaming a *score*; this is just "how many laps has this player driven here," where the raw count is the actually-interesting number).
- **Average lap time** — mean of that player's `time` across all their laps on this server (all attempts, not just their per-map bests). **Flagged as an assumption**: could instead mean "average of their best lap per map" — the two give meaningfully different numbers (raw average pulls toward warm-up/practice laps; average-of-bests reflects skill ceiling only). Defaulting to raw average since it's the more literal reading of "average lap time," implemented as such — revisit if this reads wrong in practice.

**Ranked table beyond top 3 (added 2026-07-06, per explicit request)**: below the top-3 podium, every other player with a lap on this server is listed in a real ranked table (rank/name/Server Score/laps), paginated 15 rows per page — the same structure as the Global Leaderboard's ranks-4+ table (`HasRankedLeaderboardPagination`), just scoped to this server. Its pagination uses a distinct `players` query-string page name (`?players=2`) so it doesn't collide with Latest Laps' own pagination on the same page (`?page=2`, the Livewire default).

### Maps

Unchanged — see "Current state" above.

### Latest Laps

**Renamed from "All Laps"** (see [decisions.md](decisions.md)) — a plain reverse-chronological **feed** of every lap on this server, paginated, newest first. Deliberately **not** deduplicated per (player, map): a player who ran 5 laps in a row shows up 5 times, which is correct for a feed (a brief dedup attempt was tried and reverted — see decisions.md). No filtering UI specified yet — map/player filters could be added later, but that's an implementation detail, not specced here.

## IP:Port identity (added 2026-07-07)

Every place a server's **name** is shown as its own identity label (not an incidental reference inside a table row about something else) also shows its `ip:port` right underneath/alongside it, matching the `Server::$ip`/`Server::$port` columns (see [database.md](database.md)) — this page's H1, the Servers List (`/servers`, featured card + table + mobile rows), the server-scoped Player Single variant's eyebrow, the nested Map Leaderboard's eyebrow, and Home's "Most Active Server" highlight card. Deliberately **not** added to secondary/contextual mentions of a server name inside another subject's table (e.g. the Global Leaderboard's per-row "winning server" subtitle, Player Single's Performance-by-Map/Fav Servers server column, the Lap Detail modal, or the Homepage "records"/"new content" highlight prose) — those are about a player or a lap, not about identifying a server, and repeating `ip:port` there would be clutter without adding useful context.

## Removed

- **Top 3 Fastest Laps** — originally specced as the 3 fastest raw lap times across all maps on this server. Built, then removed after review: comparing raw times across maps with drastically different lengths (~60–120s in this project's mock data) doesn't produce a meaningful "top 3" — a fast lap on a short map and a fast lap on a long map aren't comparable without normalization, which was never in scope. No normalized replacement was requested; the section is simply gone. See [decisions.md](decisions.md).

## Open items

- **"Average lap time" definition** — raw average across all attempts vs. average of per-map bests (see Top Players above).
- Whether Server Score (this doc) and Global Score ([global-ranking.md](global-ranking.md)) should be displayed together anywhere (e.g. on `players.show`) so a player can compare their server-specific standing to their overall one — not designed, just a natural follow-on question.
- Latest Laps filtering UX (by map/player) — not designed, flagged as a possible future addition rather than optional-but-needed.
