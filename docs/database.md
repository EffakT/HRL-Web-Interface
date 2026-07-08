# Database

## Source of truth

The dev DB (`redesign_hrl` in MySQL) is a **real import of the production data**, not a fresh schema built from Laravel migrations. Confirmed via `SHOW TABLES` — all 16 tables present and matching `old_schema.md` exactly. **No custom migrations have been run** beyond the stock Laravel scaffold's `users`/`cache`/`jobs` tables — most of the schema already exists for real and needs no migration to read from.

⚠️ **Do not run `php artisan migrate` blindly.** The stock scaffold's `users`/`jobs` migrations will conflict with the already-existing real tables of the same name. If you need a Laravel-convention table that's genuinely missing (e.g. `cache`), run only that specific migration file: `php artisan migrate --path=database/migrations/{file}.php`. See the `cache` table incident in [decisions.md](decisions.md).

## Real schema (as imported)

```
servers
  id, ip, port, name, type, notify_outage, notify_outage_last,
  current_map_id, live_player_count, queried_at, query_successful, deleted_at, timestamps
  -- current_map_id/live_player_count/queried_at/query_successful added 2026-07-06 (roadmap
  --    item 19, migration add_live_query_fields_to_servers_table) — a scheduled job (every
  --    minute) live-queries each server and stores the result here rather than live-fetching
  --    per request. All four nullable: a server never successfully queried yet (or currently
  --    unreachable) just has nulls, and consumers fall back to the lap-history-derived proxy.
  -- unique (ip, port) identity, enforced at the DB level (added 2026-07-07 — SEC-01 audit
  --    follow-up, see security.md) via a generated `active_since` column
  --    (`COALESCE(deleted_at, <sentinel>)`) and a unique index on (ip, port, active_since) —
  --    NOT a plain unique(ip, port), which would permanently block that ip:port from ever being
  --    reused after the server is archived. `active_since` is a DB-internal indexing column
  --    only; the app never reads or writes it directly.

maps
  id, name, label, checkpoint_count, timestamps
  -- race_type gets its own identity (added 2026-07-08, reversing the earlier "label-only"
  --    design — see decisions.md) — App\Jobs\ProcessNewLap::raceTypeMapName() suffixes `name`
  --    with `-anyorder`/`-rally` for race_type 1/2 (race_type 0, the overwhelming majority of
  --    real traffic, is a no-op suffix — existing real rows are untouched, no migration needed).
  --    Composes with the checkpoint-count fork below (e.g. `bloodgulch-anyorder-splits-6`).
  --    Real historical laps can never be retroactively attributed to a race_type — it was never
  --    persisted per-lap, only folded into a label string and a one-way hash — so pre-2026-07-08
  --    laps stay under the plain (race_type-0) row regardless of which race_type they actually
  --    were run as.
  -- unique(name), added 2026-07-08 (SEC-04 review follow-up, see security.md) — backs
  --    App\Jobs\ProcessNewLap::resolveMap()'s firstOrCreate() calls (base map and
  --    {name}-splits-{count} variants) against a concurrent duplicate. Adding it required a
  --    one-time data fix first: the real DB had one pre-existing duplicate name ("bloodgulch",
  --    ids 1 and 10) from ProcessNewLap.php-legacy's old race-type-suffixed label handling — id
  --    10 was dead (0 lap_times) and got merged/deleted by the add_unique_index_to_maps_name
  --    migration's predecessor.
  -- checkpoint_count (added 2026-07-08, SEC-04 audit follow-up, see security.md) — learned from
  --    a map's first split-bearing submission and enforced after that by
  --    App\Jobs\ProcessNewLap::resolveMap(), which establishes it via a concurrency-safe
  --    conditional UPDATE (SEC-04 review follow-up), not a plain read-then-write. Backfilled for
  --    all 9 real maps (2026-07-08) from their own historical lap_time_splits rather than left
  --    null until each map's next split-bearing submission — still null only for a map that has
  --    never had a single split-bearing lap. Splits themselves are still keyed by checkpoint_id
  --    on lap_time_splits, not stored here.

players
  id, name, hash, user_id (nullable FK to users), timestamps
  -- hash is likely an identity/dedupe key from the game server — confirm once webhook payload is inspected
  -- ⚠️ confirmed real duplicate identities: multiple `players` rows can share the same `name`
  --    with different `hash` values (e.g. "TAIIDOSH" exists as two separate player_id rows on
  --    the same server) — found while building Server Single's Latest Laps section (deduped by
  --    player_id, which correctly does NOT merge these, since they're different rows by design
  --    today). Not fixed — merging same-name players is a real feature question of its own
  --    (which hash/session should "win"?), not something to silently collapse. See decisions.md.

lap_times                 -- PB-PROGRESSION LOG, not full lap history (corrected 2026-07-06 — see below)
  id, server_id, map_id, player_id, time, submission_id (nullable, added 2026-07-07 — SEC-01
    audit follow-up, see security.md; unique together with server_id, null for every lap
    submitted without a client-supplied idempotency key), submission_hash (nullable char(64),
    added 2026-07-07 — SEC-01 fourth follow-up; canonical content fingerprint from
    App\Helpers\LapSubmissionHash, compared against an incoming resubmission's fingerprint when
    a submission_id collides, so a genuinely different payload reusing the same submission_id
    returns 409 instead of silently replaying the old lap; null for rows recorded before this
    column existed), timestamps
  -- 1657 rows / 817 players, across 1613 distinct (player,map,server) groups — average 1.03
  --    rows per group, only 26 groups have more than one row. This is NOT "every lap ever
  --    driven" (that would show far more rows per group); it's confirmed (see below) that the
  --    old webhook only inserts a row when a lap beats the player's existing best on that
  --    server+map. Best-per-(player,map,server) is still a valid derived MIN(time) query —
  --    that part of the architecture is unaffected — but "full history" was a wrong
  --    characterization of what's actually stored. See decisions.md.
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

Both pivot tables contain many duplicate `(server_id, map_id)` / `(player_id, server_id)` rows — confirmed on real data: one server had **207** `servers_maps` rows for only **9** distinct maps, and **201** `players_servers` rows for only **17** distinct players.

**Root cause confirmed 2026-07-06** by reading the old webhook job (`app/Jobs/ProcessNewLap.php-legacy`): it calls `DB::table('players_servers')->insertOrIgnore([...])` (and the same for `servers_maps`) on **every single lap submission**, not just the first time a pairing is seen. `insertOrIgnore` only skips a row when it would violate a unique/primary key constraint — but per `old_schema.md`, neither pivot table has a unique index on the pair columns, only a plain `KEY` (non-unique) on each FK individually. With no constraint to violate, `insertOrIgnore` silently inserts a fresh duplicate row every time — it was never actually deduplicating anything. Not something to "fix" by deleting rows (that's real historical data of a kind, just not deduplicated), just something every consumer must account for, and a concrete thing to actually fix (add a real unique constraint) if/when the pivot-writing logic is ported to the new webhook.

**Consequence**: `Server::maps()`, `Server::players()`, `Map::servers()`, and `Player::servers()` all call `->distinct()` in their relation definition so `->get()` / eager loading return correctly deduplicated collections automatically — don't remove that. It's still not a plain count-safe relation, though: `$server->maps()->count()` (the aggregate shortcut) does **not** honor a column-less `->distinct()` and will return the raw (duplicated) row count. For counts, use `$server->maps()->distinct('maps.id')->count('maps.id')` — a bare `->distinct()->count()` silently gives the wrong number. Verified both forms against real data before relying on this pattern anywhere.

## Laps from archived servers are excluded

Two server rows (ids 3 and 4) are soft-deleted but still referenced by historical `lap_times` rows. On map 1 alone, 54 laps reference these archived servers. A first real global-leaderboard pass tried to retain and label that history, but the user explicitly chose the simpler product rule: **if a server is trashed, treat it as though it does not exist**.

`LapTime::server()` keeps the normal soft-delete scope. Global leaderboard, Map List aggregates, and Server Single's global-record lookup all require an existing active server (`whereHas('server')` or an active-server join), so archived-server laps do not affect counts, bests, rankings, or record references.

## Key model decisions

- **History = `lap_times`, no separate PB-only table.** No pruning. This superseded an earlier (pre-schema-inspection) plan involving invented `lap_records`/`lap_record_splits`/`lap_events` tables — those never existed and are not being built. **Correction (2026-07-06)**: the *existing* real rows are a PB-progression log (one row per improvement), not literally every lap ever attempted, as originally believed — see decisions.md. **Decided**: the rebuilt webhook will log every future attempt, making this genuinely full history from that point forward.
- **Splits are per-checkpoint** (`checkpoint_id` on `lap_time_splits`), richer than the originally-planned per-map `sector_number` + single time.
- **"Most active" server**: superseded by the full spec in [most-active-server.md](most-active-server.md) (weighted activity score + recency bonus, 90-day rolling window) — this replaced the original rough placeholder ("ranked by `lap_times` row count in a rolling 30-min window"). Still replaces a separate `lap_events` table that was never built — no new table needed, it's a derived query over `lap_times`.
- **Entity naming**: `Player`, not `Driver`/`Racer` — matches the old API's terminology. "Driver" is still used as a UI display label (see [glossary.md](glossary.md)).
- **Clans/tags**: out of scope. See [scope.md](scope.md).
- **Claim-code system** (`users_players`/`users_servers`): tables preserved as-is, no feature work planned around them until explicitly revisited.
- **No multi-tenancy** — no `org_id`/league scoping anywhere.

## Webhook → job flow

### Old app's actual behavior (inspected 2026-07-06 via `app/Http/Controllers/ApiController.php-legacy` + `app/Jobs/ProcessNewLap.php-legacy`/`ProcessPlayerClaim.php-legacy`)

These `-legacy` files are reference copies of the old app's controller/jobs, not part of the live app (non-`.php` extension, not autoloaded) — kept for inspection only.

1. `POST` webhook (`ApiController::newTime`) receives the game server's IP (from the request) + a JSON payload: `{map_label, map_name, player_hash, player_name, player_time, port, race_type, splits: [{checkpoint_id, duration, startTime, endTime}, ...]}`. A hardcoded IP-remap exists for a specific internal-network NAT quirk (a specific private-network address range → a real public IP) — environment-specific, not portable as-is.
2. `ProcessNewLap` job:
   - **Live-queries the actual Halo game server over UDP** (a `QueryServer` helper) to fetch its current hostname — the server's `name` comes from this live query, not from the webhook payload.
   - `Server::firstOrCreate(['ip' => ..., 'port' => ...], ['name' => $hostname])`, updates `name` if the live-queried hostname changed.
   - `Player::firstOrCreate(['hash' => sha256($payload['player_hash'])], ['name' => $payload['player_name']])` — confirms the exact hash algorithm (`sha256` of the incoming raw hash, not stored raw).
   - Attaches `players_servers`/`servers_maps` pivot rows via `insertOrIgnore` on **every** submission — see "Duplicate pivot rows" above for why this doesn't actually dedupe.
   - `Map::firstOrCreate(['name' => $payload['map_name']], ['label' => $computedLabel])` — `label` is derived from a hardcoded machine-name → display-name alias dictionary (~18 known maps) plus a race-type suffix (`" - Any Order"` / `" - Rally"`), not copied from the payload. The alias table is in the legacy job file if it's needed again.
   - **⚠️ Only inserts a `lap_times` row if `is_null($bestTime) || $newTime < $bestTime`** — i.e. only on a genuine personal-best improvement for that (player, map, server). Non-improving laps are validated (game-server query succeeds) but otherwise silently discarded — never written anywhere. This is the confirmed root cause of the "PB-progression log, not full history" correction above.
   - Splits are only inserted alongside a new PB row (same conditional).
   - Computes leaderboard position via a `MIN(time)`-grouped subquery — same derived-read approach this project already uses, not a stored rank.
3. `ApiController::claimPlayer` + `ProcessPlayerClaim` job: a separate endpoint, hashes the incoming code the same way, finds the player's pending claim by code, and marks it claimed. Confirms claim codes are submitted through the same game-server-relay channel as lap times, not a website form. Still deferred — see scope.md — but useful context if it's ever revisited.

### Rebuilt and live (2026-07-06)

`POST /api/v1/laps` → `Api\V1\LapSubmissionController::store` → `App\Jobs\ProcessNewLap`. See [decisions.md](decisions.md) for the full implementation writeup; summary of what changed vs. the old app:

- **No login/token auth, but no longer fully open either (reversed 2026-07-07, SEC-01)** — every submission is now cross-checked against a live UDP `\query` response from the submitting ip:port before being trusted (`App\Helpers\LapSubmissionVerifier`), reusing this same UDP protocol rather than adding TLS/HMAC the Lua side can't do. See [security.md](security.md)'s "SEC-01 — HRL query verification" for the full field spec, and [decisions.md](decisions.md) for why the original "keep it open" call was reversed.
- **Logs every attempt, not just PB improvements** — `LapTime::create()` runs unconditionally now. `isNewRecord` is still computed (via the pre-insert `MIN(time)`) but only decides the response shape and whether to broadcast, not whether to write the row. Makes [most-active-server.md](most-active-server.md)'s "Valid Laps" metric genuinely meaningful instead of only ever seeing PB-improvement events.
- **A failed live query no longer discards the lap.** The old app aborted the whole submission if the UDP query failed. Now: a failed query just logs a warning and the lap is still recorded — a brand-new server gets a placeholder name (`"Unknown (ip:port)"`) until a later successful query updates it; an already-known server just keeps its last-known name.
- **Duplicate pivot rows fixed at the write site** — `$server->players()->syncWithoutDetaching([...])` / `->maps()->syncWithoutDetaching([...])` replace the old `insertOrIgnore`-with-no-unique-constraint approach (see "Duplicate pivot rows" above). No new duplicates get created going forward; existing duplicate rows are untouched.
- **Broadcasts `App\Events\LeaderboardUpdated`** (`ShouldBroadcast`, public channel `servers.{serverId}.maps.{mapId}`) only when `isNewRecord`. Reverb/Echo (roadmap item 16) isn't wired up yet — with this environment's `BROADCAST_CONNECTION=log` default, this currently just logs. The event exists now so item 16 only needs a frontend listener, not a backend change.
- **Not queued** — despite the class name, `ProcessNewLap` runs synchronously inside the request (matches what the *old* app's runtime actually did — it declared `ShouldQueue` but only ever called `->handle()` directly, never `::dispatch()`), because the game server needs its leaderboard position back in the same HTTP response.
- **Rate-limited independently from the read API** — its own `webhook` limiter (120/min/IP, more generous than the read API's 60/min, since one busy server's IP can legitimately submit far more often than a browsing client would). See [api.md](api.md) and [security.md](security.md).
- Map-label alias dictionary and race-type suffix logic ported verbatim into `ProcessNewLap::MAP_ALIASES`/`RACE_TYPE_SUFFIXES`.

~~Still open: how/whether `servers.current_map_id`-equivalent gets updated~~ — **resolved, see roadmap item 19 below** (a scheduled live query, separate from this webhook).

### `QueryServer` UDP protocol — confirmed 2026-07-06

`app/Helpers/QueryServer.php-legacy` (previously missing from the inspected legacy set, now recovered) is a **GameSpy-style server query client** — this is the standard query protocol used by Halo PC/Halo Custom Edition dedicated servers:

- Opens a UDP socket to the server's `ip:port`, sends the literal 6-byte payload `\query` (backslash-prefixed, no trailing backslash — confirmed from the legacy `socket_send($sock, "\\query", 6, ...)` call), waits up to a timeout (2s default) for a response.
- The response is one big string of backslash-delimited tokens (`explode("\\", $buffer)`), alternating key/value: e.g. `\hostname\Foo Server\numplayers\4\...\player_0\Name1\player_1\Name2\...\`. First token (index 0, before the leading `\`) is discarded.
- `numplayers` lives at a **fixed offset (index 19)** in the split array in the legacy implementation — fragile (assumes every server response has an identical fixed key layout ahead of the player list), not a keyed lookup. A rebuild should parse this properly as actual key/value pairs first, then read `numplayers`/`hostname`/etc. by key instead of by hardcoded index — far less brittle against different Halo server builds/mods that might emit keys in a different order or add extra ones.
- Player entries are indexed (`player_0`, `player_1`, ...) with parallel `score_N`/`ping_N`/`team_N` arrays offset by `numplayers * 2/4/6` slots respectively — same "positional, not keyed" fragility.
- No authentication — this is a public, unauthenticated read-only query, same trust model as the site's own read API.

This unblocked roadmap items 14 (webhook rebuild — server hostname lookup) and 19 (live server info) — no protocol reverse-engineering needed, only a modern (non-deprecated `socket_*`, Laravel-idiomatic) reimplementation with proper key/value parsing instead of the fragile fixed-offset approach.

**Real response fields confirmed empirically (2026-07-06)** by querying the actual production server (id 7 — genuinely live and reachable, ~5ms response time) with the rebuilt `App\Helpers\QueryServer`:

```
hostname, gamever, hostport, maxplayers, password, mapname, dedicated, gamemode,
game_classic, numplayers, gametype, teamplay, gamevariant, fraglimit, player_flags,
game_flags, team_t0, team_t1, score_t0, score_t1, final, queryid, sapp, sapp_flags,
nextmap, nextmode
```

The `sapp` key (`"10.2.1 PC"`) confirms this is a Halo PC dedicated server running [SAPP](http://halo.isimaster.com/) (a third-party server-side scripting/patch mod) — that's how the custom `HRLRace` game variant (`gamevariant`) and race-specific behavior (lap submission, etc.) are implemented on top of stock Halo PC.

Two fields matter for roadmap item 19: **`mapname`** (e.g. `"bloodgulch"`) matches this app's `maps.name` column format exactly — the same machine-name already used everywhere else (webhook payload, alias dictionary) — and **`numplayers`**. No confirmed empirical need to guess at a key name; item 19 is built directly against these two.

## Live server info (roadmap item 19) — done 2026-07-06

`App\Console\Commands\RefreshLiveServerInfo` (`app:refresh-live-server-info`), scheduled every minute via `routes/console.php`, live-queries every active (non-archived) server and stores the result on new `servers` columns: `current_map_id` (nullable FK to `maps`, resolved by matching the response's `mapname` against `maps.name` — **never fabricates a new `Map` row** if unmatched, since a `Map` is only ever created from an actual lap submission, per `ProcessNewLap`), `live_player_count` (from `numplayers`), `queried_at`, `query_successful`.

A scheduled job rather than live-per-request, per the reasoning already flagged when this item was added: a UDP query has real latency and can time out, unlike this app's other "derive fresh in PHP" calculators (`GlobalRanking`, `MostActiveServer`) which are cheap in-process DB aggregates with no failure mode.

**A failed query never wipes previously-known good data** — only `queried_at`/`query_successful` update; `current_map_id`/`live_player_count` are left at their last value. `ServerList` (the only current consumer) treats a **fresh** (≤5 minutes — a generous margin over the 1-minute schedule) successful query as authoritative for both "online" and "now playing," but a **fresh failed** query is still treated as authoritative for "online" specifically (it just directly confirmed the server is unreachable — more trustworthy than a lap-recency guess) while falling back to the lap-history-derived proxy for "map" (a failed query has no live map to report). Once live data goes stale (no query in >5 minutes — scheduler not running, or not yet run for a brand-new server), both fields fall back to the pre-existing proxies entirely.

`live_player_count` isn't displayed as a number in the UI yet — `ServerList`'s existing `players`/`playersRaw` fields mean "distinct players who have ever played here" (an all-time roster size used for the relative "load" bar), a different concept from "currently connected right now." Introducing a second, differently-scoped player-count concept into that card was left as a follow-up display decision rather than folded into this task.

It does, however, feed a real UI distinction (added 2026-07-06 per explicit follow-up): the **table**'s "online" means "the server process is reachable" — a fresh successful query is enough, same as when this item first landed. The **featured card** is held to a stricter bar: "you could join a race right now," which additionally requires `live_player_count > 0` when fresh live data exists. A server can legitimately show as online in the table while its "ONLINE" badge is hidden on the featured card, if it's up but empty — this is intentional, not a bug, and is exactly what a first pass surfaced (a reachable-but-empty server showing an "ONLINE" badge on the card was judged misleading for a leaderboard whose whole point is "is there a race happening").

## Live leaderboard updates (roadmap item 16) — done 2026-07-06

`App\Events\LeaderboardUpdated` (see "Webhook → job flow" above — fired only on a genuine PB/record) now actually broadcasts, via [Laravel Reverb](https://reverb.laravel.com) (a first-party, self-hosted WebSocket server speaking the Pusher protocol) and [Laravel Echo](https://laravel.com/docs/broadcasting#client-side-installation) on the frontend.

**Two public channels per event** — `servers.{serverId}.maps.{mapId}` (the exact pairing `ServerMapLeaderboard`'s nested ranking cares about) and `maps.{mapId}` (the map-only channel `MapLeaderboard`'s global ranking needs, since a PB on *any* server for that map can change it). Both channels are plain `Channel` (not `PrivateChannel`) — no auth/`routes/channels.php` authorization callback needed, since this whole site is already a fully public leaderboard.

**Both leaderboard components re-fetch on receipt, they don't patch in place.** `ServerMapLeaderboard::onLapSubmitted()` / `MapLeaderboard::onLapSubmitted()` — wired via Livewire's `#[On('echo:channel,event')]` attribute (**not** `echo-public:` — see the 2026-07-08 correction below) — just re-run the exact same ranking query `mount()` already runs (extracted into a shared private `loadLeaderboard()` method each). Simpler and more obviously correct than trying to surgically insert one new lap into an already-computed, already-tie-broken, already-paginated in-memory ranking — real scale (at most a few hundred laps per map) makes the extra query irrelevant. **As of 2026-07-08 both listen on `LapSubmitted`'s site-wide `activity` channel, not `LeaderboardUpdated`'s scoped channels** — see below for why.

**Broadcasting is queued, not synchronous** — `ShouldBroadcast` (not `ShouldBroadcastNow`) means Laravel pushes an `Illuminate\Broadcasting\BroadcastEvent` job onto the queue rather than broadcasting inline during the webhook request. This requires a queue worker actually running (`php artisan queue:work`/`queue:listen`) for updates to ever reach a browser — already true of local dev via `composer run dev`'s `queue:listen` process, but a real operational dependency worth remembering (confirmed by testing this locally: a submitted PB silently produced *zero* live-client output until a queue worker was started — the job just sat in the `jobs` table).

**Verified end-to-end without a browser**: connected directly to the local Reverb server with a small `pusher-js` script (bypassing Echo/Livewire entirely) subscribed to `maps.{id}`, submitted a real PB via the webhook with a queue worker running, and confirmed the exact `leaderboard.updated` payload arrived over the WebSocket. Separately, `LeaderboardUpdatedTest.php` (unit) asserts the event's channels/payload, and `MapLeaderboardTest.php`/`ServerMapLeaderboardTest.php` call the listener method directly (`onLapSubmitted()` as of 2026-07-08, previously `onLeaderboardUpdated()`) to prove the re-fetch logic itself is correct — Pest has no running WebSocket server to exercise the real transport, so the transport and the listener logic are verified by two different means rather than left partially untested. **This "bypass Echo/Livewire entirely" verification method is exactly why it never caught the three 2026-07-08 bugs below** — see [testing.md](testing.md).

**A real port conflict found**: Reverb's default bind port (8080, `REVERB_SERVER_PORT`) was already in use by something else on this shared host. Distinguished from `REVERB_PORT` (the *client-facing* port broadcasting.php's `reverb` connection tells PHP to connect to — the same value in a simple non-proxied setup, but a separate env var/config key for when Reverb sits behind a reverse proxy on a different public port). Moved both to 8081 for this environment — see [deployment.md](deployment.md).

### Site-wide activity broadcast — `App\Events\LapSubmitted` (added 2026-07-06, same day, on request)

`LeaderboardUpdated` only fires on a genuine PB, scoped to one server+map — fine for the two leaderboard pages, but Servers List's header stats/"MOST ACTIVE" card (Activity Score) and Home's highlights (Quick Stats, Live Stats Snapshot, records, achievements, improvements) all change on **any** logged attempt, anywhere, not just an improvement on one specific map. A component listening only for `LeaderboardUpdated` would miss most of what actually changes these aggregates.

`App\Events\LapSubmitted` fires unconditionally on every attempt (from the same place in `ProcessNewLap` that already computes `isNewRecord`), broadcasting `{server_id, map_id}` on one site-wide public channel, `activity`, as `lap.submitted`. `ServerList::loadServers()` and `Home::loadHighlights()` (the same methods `mount()` already calls, given `#[On]` listener attributes) just re-run entirely on receipt — same "re-fetch, don't patch in place" precedent as the two leaderboard pages. **As of 2026-07-08, `MapLeaderboard`/`ServerMapLeaderboard` listen here too** (see below), since `LeaderboardUpdated` alone left their lap totals stale on non-PB attempts.

Verified the same way as `LeaderboardUpdated`: a standalone `pusher-js` script subscribed to `activity`, a real webhook submission, confirmed payload arrival — plus `LapSubmittedTest.php` (unit, channel/payload) and a `loadServers()`/`loadHighlights()` listener test in each component's existing test file.

### Three more live-update bugs found and fixed (2026-07-08)

Once OPS-01 (Reverb/queue/scheduler durably running under supervisor) was verified end-to-end, real testing against a live page surfaced three further bugs in the wiring above, none of them caught by either verification method described throughout this section (both deliberately bypass Echo/Livewire — see [testing.md](testing.md) for the resulting coverage gap):

1. **Every `#[On('echo-public:...')]` attribute was missing the leading dot Echo requires for a custom `broadcastAs()` name.** Without it, Echo subscribed to `App.Events.lap.submitted`/`App.Events.leaderboard.updated` instead of the literal `lap.submitted`/`leaderboard.updated` names actually broadcast. Fixed: `,.lap.submitted` / `,.leaderboard.updated`.
2. **`echo-public:` was never a real Livewire 4 channel-type prefix at all** — confirmed by reading `vendor/livewire/livewire/dist/livewire.esm.js`'s Echo bridge directly; only `channel` (from plain `echo:`), `private`, `encryptedPrivate`, `presence`, and `notification` are recognized, so every listener silently hit Livewire's "Echo channel type not yet supported" `console.warn` and did nothing. Fixed: `echo-public:` → `echo:` everywhere.
3. **Three listeners crashed once events could actually arrive**: `ServerShow::loadServerData(?Server $server = null)`, `PlayerShow::loadProfile(?Player $player = null)`, `ServerPlayerShow::loadProfile(?Server $server = null, ?Player $player = null)` were themselves the `#[On(...)]` targets, but Livewire's Echo bridge dispatches the broadcast payload array as the listener's first argument — a `TypeError` against those model type hints. Fixed with parameterless `onLapSubmitted()` wrappers; `mount()` still calls the real methods directly with real models.

**Separately, `MapLeaderboard`/`ServerMapLeaderboard` were retargeted from `LeaderboardUpdated`'s scoped channels to `LapSubmitted`'s site-wide `activity` channel** (a fourth, independent bug, not part of the three above): their `$totalLaps` figure ("SHOWING X / Y LAPS") only updated on a genuine PB, since `LeaderboardUpdated` never fires for a non-improving attempt. `LapSubmitted` is a strict superset — it fires whenever `LeaderboardUpdated` does, plus every other attempt — so switching to it with the same reload logic fixes the staleness with no loss of correctness, at the cost of being slightly more chatty (every open leaderboard page re-fetches on any lap anywhere, not just its own server+map). `LeaderboardUpdated` itself is unchanged and still broadcasts — see its docblock for why it's kept despite no longer having a direct Livewire consumer.

**A fifth, related bug in the same investigation**: `RefreshLiveServerInfo` (the scheduled live-status poll, roadmap item 19) never broadcast anything at all, so an open Servers List page only picked up an online/current-map/player-count change whenever an unrelated lap happened to be submitted somewhere and triggered `ServerList`'s `lap.submitted` listener — a quiet period with no submissions left it stale despite this command updating the underlying rows every minute. Fixed with a new `App\Events\ServerStatusRefreshed` (`ShouldBroadcast`, `activity` channel, `server-status.refreshed`), dispatched once per command run (not per server), and a second `#[On(...)]` attribute on `ServerList::loadServers()` (Livewire's `On` attribute is repeatable, so one method can carry both listeners).

Full incident detail, including exact root-cause investigation steps, in [decisions.md](decisions.md).

### Every remaining page wired up (2026-07-07)

`ServerShow`, `ServerPlayerShow`, `PlayerShow`, `PlayerList`, and `MapList` had no live-update listener at all until now — real-time updates only reached the two leaderboard pages, Servers List, and Home. Each now listens on the same site-wide `activity` channel (`#[On('echo-public:activity,lap.submitted')]`), re-running its own `mount()` logic on receipt, same "re-fetch, don't patch in place" precedent as everywhere else — no new channels or events needed, since every one of these pages shows aggregate/ranking data that any lap anywhere can move (global rank, server score, per-map best times), not just laps scoped to one specific server+map.

Mechanically: each component's `mount()` body was extracted into its own `load*()` method carrying the `#[On]` attribute (e.g. `ServerShow::loadServerData()`, `PlayerShow::loadProfile()`), called once from `mount()` and again on every `activity` broadcast. Pages with their own paginated sub-queries (`ServerShow`'s Latest Laps, `PlayerList`'s ranked-player pagination) don't need a separate listener for those — any listener method firing triggers a full Livewire re-render, which re-runs those queries anyway.

Confirmed end-to-end this was actually necessary, not just theoretical: found via a real webhook submission to a real server (id 12) while watching its Server Single page live in a browser — nothing updated, because that page genuinely had no listener wired up (unlike this discovery process, this wasn't a WebSocket/proxy problem — REL-01's nginx fix, verified separately via a real `101 Switching Protocols` handshake, was already working correctly).

## Global Player Ranking (planned)

A cross-map ranking/points system, derived entirely from `lap_times` (no new stored "score" as the source of truth) — continues this doc's existing full-history/derived-reads philosophy rather than introducing a new stored-and-drifting number. Full spec: [global-ranking.md](global-ranking.md).
