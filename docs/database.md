# Database

## Source of truth

The dev DB (`redesign_hrl` in MySQL) is a **real import of the production data**, not a fresh schema built from Laravel migrations. Confirmed via `SHOW TABLES` — all 16 tables present and matching `old_schema.md` exactly. **No custom migrations have been run** beyond the stock Laravel scaffold's `users`/`cache`/`jobs` tables — most of the schema already exists for real and needs no migration to read from.

⚠️ **Do not run `php artisan migrate` blindly.** The stock scaffold's `users`/`jobs` migrations will conflict with the already-existing real tables of the same name. If you need a Laravel-convention table that's genuinely missing (e.g. `cache`), run only that specific migration file: `php artisan migrate --path=database/migrations/{file}.php`. See the `cache` table incident in [decisions.md](decisions.md).

## Real schema (as imported)

```
servers
  id, ip, port, name, type, notify_outage, notify_outage_last, deleted_at, timestamps
  -- no current_map_id column; "current map" must be derived (most recent lap_times row per server)
  --    or a column added later if a live signal from the game server exists

maps
  id, name, label, timestamps
  -- no sector_count column; splits are keyed by checkpoint_id on lap_time_splits instead

players
  id, name, hash, user_id (nullable FK to users), timestamps
  -- hash is likely an identity/dedupe key from the game server — confirm once webhook payload is inspected
  -- ⚠️ confirmed real duplicate identities: multiple `players` rows can share the same `name`
  --    with different `hash` values (e.g. "TAIIDOSH" exists as two separate player_id rows on
  --    the same server) — found while building Server Single's Latest Laps section (deduped by
  --    player_id, which correctly does NOT merge these, since they're different rows by design
  --    today). Not fixed — merging same-name players is a real feature question of its own
  --    (which hash/session should "win"?), not something to silently collapse. See decisions.md.

lap_times                 -- FULL HISTORY, kept forever (source of truth)
  id, server_id, map_id, player_id, time, timestamps
  -- 1657 rows / 817 players at last check (confirmed via Eloquent, see Models below).
  --    Best-per-(player,map,server) is a derived MIN(time) query, never a stored/upserted row.
  -- ⚠️ created_at/updated_at are DATE columns, not DATETIME/TIMESTAMP — there is NO time-of-day
  --    precision on when a lap was submitted, only the day. Confirmed via `SHOW COLUMNS` and by
  --    querying real rows (every created_at reads as midnight). This is a real constraint, not
  --    a modeling choice — see "Known constraint: lap timestamp precision" below for what it affects.

lap_time_splits           -- one row per checkpoint per lap
  id, lap_time_id, checkpoint_id, duration, start_time, end_time, timestamps
  -- ⚠️ sparse: only 64 of 1657 real lap_times rows have any splits recorded (~4%) — confirmed
  --    while building real split comparison for Server Single's Lap Detail modal. checkpoint_id
  --    values ARE consistent within a given map across different laps (e.g. map 1's laps that
  --    have splits all use checkpoints 1-5), so real per-checkpoint comparison is valid when
  --    data exists — it's just usually absent. As of this check, no single map had both a lap
  --    and that map's own course-record lap with splits simultaneously, so real split comparison
  --    on Server Single almost always falls back to "No split data available" in practice today.

players_servers           -- pivot: which players have played on which servers
servers_maps              -- pivot: which maps run on which servers

users, password_resets, sessions, jobs, failed_jobs, migrations, logs
  -- standard Laravel 6 tables + a custom `logs` table (instance/channel/level enum/context) —
  --    confirm whether to port this or replace with standard Laravel logging

users_players, users_servers   -- claim-code ownership system, see below
```

## Eloquent models (built)

All models exist under `app/Models/`, one per real table, with matching factories under `database/factories/` (for test data only — the dev DB itself is real imported data, never seeded). No migrations were written; every table already exists in the real schema.

| Model | Table | Notes |
|---|---|---|
| `Server` | `servers` | `SoftDeletes` (has `deleted_at`). `maps()`/`players()` are `belongsToMany` via the pivot tables below, both with `->distinct()` baked in — see "Duplicate pivot rows" below, don't remove it. `lapTimes()`, `claims()` (→ `ServerClaim`). |
| `Map` | `maps` | `servers()` belongsToMany (`->distinct()`, same caveat), `lapTimes()` hasMany. |
| `Player` | `players` | `user()` belongsTo (nullable), `servers()` belongsToMany (`->distinct()`, same caveat), `lapTimes()` hasMany, `claims()` (→ `PlayerClaim`). |
| `LapTime` | `lap_times` | `server()`/`map()`/`player()` belongsTo, `splits()` hasMany. Global queries explicitly require an active `server()`; laps attached to soft-deleted servers are ignored. |
| `LapTimeSplit` | `lap_time_splits` | `lapTime()` belongsTo. |
| `PlayerClaim` | `users_players` | `SoftDeletes`. Not a plain pivot — has its own `id`/`claim_code`/`claimed_at`/`deleted_at`, modeled as a first-class model with `belongsTo(User)`/`belongsTo(Player)` rather than forced into a `belongsToMany`. Structural only — claim-code feature work is still deferred, see [scope.md](scope.md). |
| `ServerClaim` | `users_servers` | Same shape as `PlayerClaim`, for `servers`. |
| `User` (existing) | `users` | Extended with `players()` hasMany, `playerClaims()`/`serverClaims()` hasMany. |

`servers_maps` and `players_servers` are plain two-FK pivot tables (no extra columns beyond timestamps) — accessed only via `belongsToMany`, no dedicated pivot model needed for either.

## Lap timestamp precision — resolved going forward, historical rows stay day-only

`lap_times.created_at`/`updated_at` were originally `DATE` columns — day precision only, every real row's `created_at` read as midnight regardless of when the lap actually happened. **Fixed for future data**: the `widen_lap_times_timestamps_to_datetime` migration widened both columns to `TIMESTAMP`. Going forward, once the webhook pipeline exists (roadmap.md item 7/14), each `lap_times` insert will capture the actual time it was received — treated as close enough to "when the lap happened" for this project's purposes, per explicit decision (no need for a separate "precise submission time" column or payload field).

**Historical rows imported before this migration are unaffected and unrecoverable** — MySQL preserved their existing date values with an implicit `00:00:00` time component; there's no way to retroactively know what time of day those laps actually happened. This means:

- Any "relative time ago" display (Homepage's "Latest / Current Records" block, Player Single's "Last Active") will only be genuinely accurate (hour/minute-level) for laps submitted **after** this migration ran (2026-07-05). Laps from before that point will always compute as midnight-based, which could show misleadingly precise-looking but wrong relative times (e.g. "14h ago" for a lap that actually happened at some unknown time on that day) if the relative-time formatter isn't day-aware for old rows.
- Not a blocker for building the real queries, but worth being deliberate about: when the historical-record-breaking-events derivation (roadmap.md item 13) is eventually implemented, it should probably format historical (pre-migration) entries as day-level ("3 days ago" / a date) and only use hour/minute-level phrasing for lap rows recorded after the migration. Flagged here rather than in roadmap's open questions now, since the *schema* problem is solved — what's left is a display-logic nuance for whoever builds that derivation.

## Duplicate pivot rows in `servers_maps` / `players_servers`

Both pivot tables contain many duplicate `(server_id, map_id)` / `(player_id, server_id)` rows — confirmed on real data: one server had **207** `servers_maps` rows for only **9** distinct maps, and **201** `players_servers` rows for only **17** distinct players. Almost certainly the old app inserted a new pivot row every time it *saw* a map/player on a server again, rather than upserting or checking for an existing row first — not something to "fix" by deleting rows (that's real historical data of a kind, just not deduplicated), just something every consumer must account for.

**Consequence**: `Server::maps()`, `Server::players()`, `Map::servers()`, and `Player::servers()` all call `->distinct()` in their relation definition so `->get()` / eager loading return correctly deduplicated collections automatically — don't remove that. It's still not a plain count-safe relation, though: `$server->maps()->count()` (the aggregate shortcut) does **not** honor a column-less `->distinct()` and will return the raw (duplicated) row count. For counts, use `$server->maps()->distinct('maps.id')->count('maps.id')` — a bare `->distinct()->count()` silently gives the wrong number. Verified both forms against real data before relying on this pattern anywhere.

## Laps from archived servers are excluded

Two server rows (ids 3 and 4) are soft-deleted but still referenced by historical `lap_times` rows. On map 1 alone, 54 laps reference these archived servers. A first real global-leaderboard pass tried to retain and label that history, but the user explicitly chose the simpler product rule: **if a server is trashed, treat it as though it does not exist**.

`LapTime::server()` keeps the normal soft-delete scope. Global leaderboard, Map List aggregates, and Server Single's global-record lookup all require an existing active server (`whereHas('server')` or an active-server join), so archived-server laps do not affect counts, bests, rankings, or record references.

## Key model decisions

- **History = full `lap_times`, kept indefinitely.** No pruning, no separate PB-only table. This superseded an earlier (pre-schema-inspection) plan involving invented `lap_records`/`lap_record_splits`/`lap_events` tables — those never existed and are not being built. See [decisions.md](decisions.md) for why.
- **Splits are per-checkpoint** (`checkpoint_id` on `lap_time_splits`), richer than the originally-planned per-map `sector_number` + single time.
- **"Most active" server**: superseded by the full spec in [most-active-server.md](most-active-server.md) (weighted activity score + recency bonus, 90-day rolling window) — this replaced the original rough placeholder ("ranked by `lap_times` row count in a rolling 30-min window"). Still replaces a separate `lap_events` table that was never built — no new table needed, it's a derived query over `lap_times`.
- **Entity naming**: `Player`, not `Driver`/`Racer` — matches the old API's terminology. "Driver" is still used as a UI display label (see [glossary.md](glossary.md)).
- **Clans/tags**: out of scope. See [scope.md](scope.md).
- **Claim-code system** (`users_players`/`users_servers`): tables preserved as-is, no feature work planned around them until explicitly revisited.
- **No multi-tenancy** — no `org_id`/league scoping anywhere.

## Webhook → job flow (planned, not yet built)

The webhook/job mechanism already exists in the old app but **hasn't been inspected yet** (deferred — access pending). Planned flow once ported/rebuilt:

1. Webhook controller validates payload, dispatches `ProcessLapSubmitted` job (fast response, non-blocking).
2. Job:
   - Upserts player if new (matches on `hash`, tentatively — confirm once payload shape is known).
   - Inserts a new `lap_times` row — always insert, never upsert/overwrite.
   - Inserts corresponding `lap_time_splits` rows.
   - Checks whether the new lap is a PB and/or new course record (via the derived `MIN(time)` query) to decide whether to broadcast.
   - If so, broadcasts `LeaderboardUpdated` (map+server scoped channel) via Reverb/Echo.

Open questions tracked in [roadmap.md](roadmap.md): exact webhook payload shape, how (or whether) `servers.current_map_id`-equivalent gets updated.

## Global Player Ranking (planned)

A cross-map ranking/points system, derived entirely from `lap_times` (no new stored "score" as the source of truth) — continues this doc's existing full-history/derived-reads philosophy rather than introducing a new stored-and-drifting number. Full spec: [global-ranking.md](global-ranking.md).
