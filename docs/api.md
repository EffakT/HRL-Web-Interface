# API

## Status

**Implemented (2026-07-06)**, versioned under `/api/v1`, public and read-only. See [decisions.md](decisions.md) for the implementation notes and [roadmap.md](roadmap.md) item 15.

## Old API (for reference, replaced)

Public API at `/docs` on the old site (Players, Maps, Servers endpoints). Known issue, now fixed: the Player/Server resources had a JSON key bug — `"name "` (trailing space) instead of `"name"`. Auth required a per-user API token generated in profile — the new API drops this (see "Auth" below).

## Endpoints

```
GET  /api/v1/servers
GET  /api/v1/maps
GET  /api/v1/maps/{map}/leaderboard[?server={serverId}]
GET  /api/v1/laps/{lapTime}
POST /api/v1/laps
```

### `GET /api/v1/servers`

Every active (non-archived) server with real, derived stats — `id`, `name`, `total_laps`, `total_players`, `maps_played`, `last_active_at` (ISO 8601, `null` if the server has no laps at all). No pagination — real scale is a handful of servers.

### `GET /api/v1/maps`

Every map (2026-07-14), paginated — `id`, `name`, `label`, `checkpoint_count`, `total_laps`. Unlike `GET /api/v1/servers` (a handful of rows, deliberately unpaginated), the number of `Map` rows genuinely grows over time — a checkpoint-count mismatch or `race_type` variant each forks its own `{name}-splits-{count}`/`-anyorder`/`-rally` row (see [security.md](security.md), [decisions.md](decisions.md)) — so this is real DB-level pagination (`Map::paginate()`), same `?page=`/`?per_page=` bounds as the leaderboard endpoint below (default 50, capped 100). Exists mainly so a client that only knows a map's `id` can discover its `name` (or vice versa) without already knowing which endpoint to call next.

### `GET /api/v1/maps/{map}/leaderboard`

The **global** leaderboard for one map — every player's single best lap across all active servers, ranked, same tie-break as every other real leaderboard in this app (earliest lap wins a tie). Pass `?server={serverId}` for that server's **nested** leaderboard instead (see [architecture.md](architecture.md)'s global-vs-nested split) — resolves this doc's earlier open question in favor of a query parameter over a second endpoint, since it's the exact same underlying calculation just scoped differently.

**`{map}` accepts either the numeric id or the map's real `name`** (2026-07-14, e.g. `/api/v1/maps/bloodgulch/leaderboard`) — `App\Models\Map::resolveRouteBinding()` routes a plain-digit value to `id`, anything else to `name`. Lets a caller who already knows the name (the common case — that's what a game server or a human recognizes, not an opaque id) skip a prior `GET /api/v1/maps` round trip just to look up the id first.

**`?port={port}` scopes to "my own" nested leaderboard** (2026-07-14), an alternative to `?server={id}` for a requester that IS a game server and doesn't know its own `servers.id`. Resolves exactly the way `POST /api/v1/laps` already identifies a submitting game server: the request's real IP (rewritten through `ResolveSubmittingIp` for known NAT addresses, same as the webhook) plus the port the server reports about itself. Takes precedence over `?server=` if both are given. Returns `404` if the ip:port doesn't match any registered `Server` row — a claimed identity that doesn't resolve is treated as a real error, not silently downgraded to the unscoped global leaderboard.

Each entry: `rank`, `lap_id`, `player` (`id`, `name`), `server` (`id`, `name`), `time` (raw seconds), `time_formatted`, `gap` (seconds behind rank 1, `0` for rank 1 itself), `set_at` (ISO 8601).

**Paginated** (PERF-03 audit follow-up, 2026-07-08) — `?page={n}` and `?per_page={n}` (default 50, capped at 100 regardless of what's requested). Standard Laravel resource-collection pagination envelope: `data` (this page's entries) plus `links`/`meta` (`current_page`, `last_page`, `per_page`, `total`, etc.). This only bounds response size, not the underlying computation — `GlobalRanking::mapLeaderboard()` still ranks every qualifying lap for the map before this slices out the requested page (same in-memory `LengthAwarePaginator` approach the equivalent Livewire leaderboards already use via `HasRankedLeaderboardPagination`); see [performance.md](performance.md) for when the computation itself, not just the response, would need to change.

Backed by a new `App\Models\GlobalRanking::mapLeaderboard()` calculator method — the third occurrence of "rank every player's best lap on one map" (after `MapLeaderboard` and `ServerMapLeaderboard`'s own inline, UI-formatted versions), so per [coding-standards.md](coding-standards.md)'s "extract on the second genuine duplicate" rule, this gives the API its own canonical, tested, raw-data source rather than a fourth ad-hoc copy.

### `GET /api/v1/laps/{lapTime}`

One specific lap's full detail — `id`, `time`, `time_formatted`, `player`, `map`, `server`, `set_at`, `splits` (per-checkpoint `checkpoint_id`/`duration`, empty array for the ~96% of real laps with no split data — see [database.md](database.md)). Uses the real `lap_times.id` directly, resolving this doc's earlier open question ("does this need a different name now that 'record' isn't a stored row") — it does not; this was never about a course record, just one specific submitted lap.

**Not scoped to active servers** — deliberately different from the leaderboard endpoint above. A lap's historical existence doesn't depend on whether its server was later archived (matches this app's "full history, never pruned" philosophy), so this still returns the real server name even for an archived server. `LapTime::server()` is a plain (non-`withTrashed`) relation by design — every leaderboard read in this app treats an archived server's laps as nonexistent — so the controller loads the server explicitly with `withTrashed()` rather than changing that shared relation.

### `POST /api/v1/laps`

The lap-submission webhook — a Halo game server posting a completed lap (roadmap item 14, see [database.md](database.md)'s "Webhook → job flow" and [decisions.md](decisions.md) for the full rebuild writeup). Not part of the read API's request/response shape family above — this is a write endpoint aimed at game servers, not browsers.

Body (flat JSON, no wrapper): `map_name`, `player_hash`, `player_name`, `player_time`, `port`, `race_type` (0/1/2, defaults to 0), `splits` (optional array of `{checkpoint_id, duration, startTime, endTime}`), `hrl_token` (SEC-01 verification, see [security.md](security.md) — optional until `enforce` is on), `submission_id` (idempotency key, optional until `enforce` is on, required after). `map_label` is accepted but ignored — the display label is always computed server-side from `map_name` via a hardcoded alias dictionary + race-type suffix. Confirmed against the actual deployed Lua/SAPP client (`hrl.lua`, 2026-07-07) — this is the real wire format, not a reconstruction.

Response: `{success, isNewRecord, lapTime, bestTime, leaderboardPosition: {position, total, top_time?, difference?}, globalLeaderboardPosition: {position, total, top_time?, difference?}, personalBest: {time, previousTime, isNewRecord, improvement}}` — `top_time` is snake_case deliberately (confirmed against the real Lua client's `lb.top_time` read, 2026-07-07; the legacy app used the same casing, see [decisions.md](decisions.md)). Every submitted lap is logged (not just personal-best improvements — a deliberate change from the old app, see [database.md](database.md)), but `isNewRecord` and the broadcast (below) still only fire on a genuine improvement.

`leaderboardPosition` stays scoped to the submitting server (the nested leaderboard); `globalLeaderboardPosition` is the same shape computed across every active server for this map (the global leaderboard), added 2026-07-14 so a single response carries both without the Lua client needing a second lookup. It's ranked using the player's GLOBAL best time (see `personalBest` below), not their server-scoped one — a player whose fastest time was set on a different server is still correctly ranked using that faster time, not a slower one just because it happened on the submitting server.

`personalBest` is **global-scoped** (2026-07-14 decision): `time`/`previousTime`/`isNewRecord`/`improvement` reflect the player's best across every active server they've played on for this map, not just the submitting server — a player's PB shouldn't reset just because they're playing on a server they haven't visited before. `previousTime` is this player's global PB before this submission (`null` only on their very first-ever lap for this map, on any server), and `improvement` is seconds shaved off it (`null` unless `isNewRecord` is true and a previous global PB existed). This is deliberately a SEPARATE computation from the top-level `bestTime`/`isNewRecord` fields, which stay server-scoped exactly as before (relied on by the currently-deployed Lua client and the `LeaderboardUpdated` broadcast) — only `personalBest`/`globalLeaderboardPosition` use the global calculation. All of this is purely additive — no existing top-level key changed shape or meaning, so the currently-deployed Lua client's reads (`isNewRecord`, `lapTime`, `lb.top_time`) are unaffected.

Live-queries the actual game server over UDP (see [database.md](database.md)'s "QueryServer UDP protocol") to fetch its real hostname — the server's stored `name` comes from this live query, not from the payload. A failed query no longer drops the lap (unlike the old app); it just falls back to a placeholder/existing name.

Fires `App\Events\LeaderboardUpdated` (`ShouldBroadcast`) only when `isNewRecord`, and `App\Events\LapSubmitted` on every attempt — both real over Reverb/Echo since roadmap item 16, see [database.md](database.md)'s "Live leaderboard updates" section.

**No login/token-based auth**, same as the read endpoints and the old app's equivalent. It does now cross-check every submission against a live UDP query to the submitting game server (see [security.md](security.md)'s "SEC-01 — HRL query verification") — not a credential, but the closest thing to authentication available without TLS/HMAC support on the Lua side. **Its own tiered rate limits** (not the read API's flat 60/min) — see "Rate limiting" below.

## Auth

**No login/API-token auth, still.** The whole site is already a fully public leaderboard with no login system — these endpoints expose nothing the website itself doesn't already show. Per-user API tokens (the old app's approach) don't make sense to port until a real auth/account system exists (still an open question — see [roadmap.md](roadmap.md)).

The lap-submission webhook is the one exception to "rate limiting is the only protection" — a 2026-07-07 audit flagged the fully-open webhook as critical (SEC-01), so it now also requires a live UDP query cross-check (see [security.md](security.md)) before a submission is trusted, in addition to its rate limit. The read endpoints remain unauthenticated by design — they only expose data the site already shows publicly.

## Rate limiting

The read endpoints: 60 requests/minute per IP, via Laravel's built-in `throttle:api` middleware (`RateLimiter::for('api', ...)` in `AppServiceProvider`). A starting point, not a measured/tuned value — revisit if real usage says otherwise.

The webhook (`POST /laps`): its own, more generous, **tiered** `webhook` limiter (since 2026-07-07's second SEC-01 follow-up — see [security.md](security.md)), since it's machine-to-machine (a busy game server with several racers can legitimately submit far more often than a browsing client would). A source starts in the strict "unverified" tier (30/min per IP, 15/min per ip:port, 2/sec burst) and only earns the more generous "verified" tier (300/min, 120/min, 10/sec burst) once a request from that exact ip:port has actually passed HRL query verification — cached for 5 minutes. Verification itself still runs on every single request regardless of tier; the marker only ever raises the ceiling. Within either tier, the per-ip:port limit (so multiple distinct game servers behind one host IP don't share a budget) and the coarser per-IP ceiling (so rotating the unverified `port` value can't bypass the limit, even once "verified") both apply together. Explicitly opts out of the read API's `throttle:api` (`->withoutMiddleware('throttle:api')`) so these budgets are independent of it.

## Versioning

`/api/v1/...` from day one, per Laravel Boost's default guidance (Eloquent API Resources + API versioning) — cheap to do upfront, expensive to retrofit once real consumers exist.

Adding pagination to `GET /maps/{map}/leaderboard` (above) technically changed its response shape (a flat array under `data` gained `links`/`meta` siblings) while still under `v1` rather than bumping to `v2` — accepted deliberately: no real external consumers of this endpoint are known to exist yet (it's brand new, staging-only), and `data` itself is unchanged for any client that was only reading that key.

## Resolved open questions

- ~~Auth/token strategy~~ — see "Auth" above.
- ~~Whether `/api/laps/{lapRecord}` needs a different name~~ — no; renamed the route parameter to `{lapTime}` for clarity (it addresses a `lap_times` row directly), but the underlying concept was already right.
- ~~Rate limiting strategy~~ — see "Rate limiting" above.
- ~~Whether the server-scoped vs. global leaderboard split needs separate endpoints~~ — one endpoint, `?server=` query parameter.
