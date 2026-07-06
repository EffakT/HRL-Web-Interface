# API

## Status

**Implemented (2026-07-06)**, versioned under `/api/v1`, public and read-only. See [decisions.md](decisions.md) for the implementation notes and [roadmap.md](roadmap.md) item 15.

## Old API (for reference, replaced)

Public API at `/docs` on the old site (Players, Maps, Servers endpoints). Known issue, now fixed: the Player/Server resources had a JSON key bug — `"name "` (trailing space) instead of `"name"`. Auth required a per-user API token generated in profile — the new API drops this (see "Auth" below).

## Endpoints

```
GET /api/v1/servers
GET /api/v1/maps/{map}/leaderboard[?server={serverId}]
GET /api/v1/laps/{lapTime}
```

### `GET /api/v1/servers`

Every active (non-archived) server with real, derived stats — `id`, `name`, `total_laps`, `total_players`, `maps_played`, `last_active_at` (ISO 8601, `null` if the server has no laps at all). No pagination — real scale is a handful of servers.

### `GET /api/v1/maps/{map}/leaderboard`

The **global** leaderboard for one map — every player's single best lap across all active servers, ranked, same tie-break as every other real leaderboard in this app (earliest lap wins a tie). Pass `?server={serverId}` for that server's **nested** leaderboard instead (see [architecture.md](architecture.md)'s global-vs-nested split) — resolves this doc's earlier open question in favor of a query parameter over a second endpoint, since it's the exact same underlying calculation just scoped differently.

Each entry: `rank`, `lap_id`, `player` (`id`, `name`), `server` (`id`, `name`), `time` (raw seconds), `time_formatted`, `gap` (seconds behind rank 1, `0` for rank 1 itself), `set_at` (ISO 8601). No pagination in v1 — real scale per map is at most a few hundred players; revisit if that changes.

Backed by a new `App\Models\GlobalRanking::mapLeaderboard()` calculator method — the third occurrence of "rank every player's best lap on one map" (after `MapLeaderboard` and `ServerMapLeaderboard`'s own inline, UI-formatted versions), so per [coding-standards.md](coding-standards.md)'s "extract on the second genuine duplicate" rule, this gives the API its own canonical, tested, raw-data source rather than a fourth ad-hoc copy.

### `GET /api/v1/laps/{lapTime}`

One specific lap's full detail — `id`, `time`, `time_formatted`, `player`, `map`, `server`, `set_at`, `splits` (per-checkpoint `checkpoint_id`/`duration`, empty array for the ~96% of real laps with no split data — see [database.md](database.md)). Uses the real `lap_times.id` directly, resolving this doc's earlier open question ("does this need a different name now that 'record' isn't a stored row") — it does not; this was never about a course record, just one specific submitted lap.

**Not scoped to active servers** — deliberately different from the leaderboard endpoint above. A lap's historical existence doesn't depend on whether its server was later archived (matches this app's "full history, never pruned" philosophy), so this still returns the real server name even for an archived server. `LapTime::server()` is a plain (non-`withTrashed`) relation by design — every leaderboard read in this app treats an archived server's laps as nonexistent — so the controller loads the server explicitly with `withTrashed()` rather than changing that shared relation.

## Auth

**Decided: none, for now.** The whole site is already a fully public leaderboard with no login system — these endpoints expose nothing the website itself doesn't already show. Per-user API tokens (the old app's approach) don't make sense to port until a real auth/account system exists (still an open question — see [roadmap.md](roadmap.md)). Rate limiting, not auth, is the actual protection against abuse (see below). Revisit if a future feature needs to distinguish *who* is calling the API, not just *how often*.

## Rate limiting

60 requests/minute per IP, via Laravel's built-in `throttle:api` middleware (`RateLimiter::for('api', ...)` in `AppServiceProvider`). A starting point, not a measured/tuned value — revisit if real usage says otherwise.

## Versioning

`/api/v1/...` from day one, per Laravel Boost's default guidance (Eloquent API Resources + API versioning) — cheap to do upfront, expensive to retrofit once real consumers exist.

## Resolved open questions

- ~~Auth/token strategy~~ — see "Auth" above.
- ~~Whether `/api/laps/{lapRecord}` needs a different name~~ — no; renamed the route parameter to `{lapTime}` for clarity (it addresses a `lap_times` row directly), but the underlying concept was already right.
- ~~Rate limiting strategy~~ — see "Rate limiting" above.
- ~~Whether the server-scoped vs. global leaderboard split needs separate endpoints~~ — one endpoint, `?server=` query parameter.
