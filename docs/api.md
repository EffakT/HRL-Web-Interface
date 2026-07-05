# API

## Status

**Not built yet.** Nothing in this doc is implemented — this captures the plan as it stands.

## Old API (for reference, being replaced)

Public API at `/docs` on the old site (Players, Maps, Servers endpoints). Known issue to fix: the Player/Server resources have a JSON key bug — `"name "` (trailing space) instead of `"name"`. Auth requires a per-user API token generated in profile.

## Planned new endpoints

Exact response shapes are **TBD** (tracked in [roadmap.md](roadmap.md)):

```
GET /api/servers
GET /api/maps/{map}/leaderboard
GET /api/laps/{lapRecord}
```

Requirements:
- Fix the `"name "` trailing-space key bug while rebuilding.
- Follow Laravel Boost's guidance: Eloquent API Resources + API versioning (default, unless a strong reason emerges to deviate).
- Reflect the real leaderboard model — "leaderboard" endpoints return **derived** best-lap-per-player queries (`MIN(time) GROUP BY ...`), not a stored table, per the full-history decision in [database.md](database.md).
- Decide whether the server-scoped vs. global leaderboard split (see [architecture.md](architecture.md)) needs to be reflected as separate API endpoints or a query parameter on one endpoint.

## Open questions

- Auth/token strategy for the new API (port the old per-user-token approach, or something else).
- Whether `/api/laps/{lapRecord}` needs a different name now that "record" isn't a stored row (see [database.md](database.md)) — the URL should probably use `lap_times.id` directly instead.
- Rate limiting strategy (see [security.md](security.md)).
