# Testing

## Status

**Test/analysis tooling is now in place** (2026-07-05): Pest, PHPStan (via Larastan), and Rector are installed and configured, with a first real automated test (`tests/Feature/RoutesTest.php`) replacing the manual curl-based route checking used throughout the build. Semgrep is configured but **not installed** — see below.

## Tools

### Pest — `composer test` / `php artisan test --compact`

`tests/Feature/RoutesTest.php` covers every real route (dataset-driven, one row per route) plus two assertions distinguishing the nested vs. global leaderboard eyebrow text. This directly replaces the "curl every route, grep for expected markup" manual process documented in git history — extend this file (not a new one) as more routes/behavior need covering, following the existing dataset pattern.

Real-data component coverage also lives in focused Feature files: `ServerListTest.php` checks server recency/player derivations plus (2026-07-06) live-vs-proxy precedence for online status/current map; `ServerShowTest.php` checks active-only global record references; `MapListTest.php` checks active-server aggregation and zero-lap exclusion; `MapLeaderboardTest.php` checks cross-server PB ranking, ties, archived-server exclusion, links, splits, the podium split empty state, and pagination/index preservation; `LapTimeSplitCompareTest.php` covers every comparison branch; `ApiTest.php` covers the three read endpoints; `LapSubmissionTest.php` covers the lap-submission webhook; `RefreshLiveServerInfoTest.php` covers the live-server-info scheduled command. The suite currently passes **86 tests / 229 assertions** (2026-07-06, up from 30/80 — see below).

**Phase 2's four calculators are now directly tested (2026-07-06, roadmap item 17)**, not just exercised indirectly via route smoke tests:
- `GlobalRankingTest.php` — points-table/interpolation boundaries (ranks 1, 10, 11, 25, 26, 50, 51+), per-map best-lap ranking (ignoring a player's slower attempts), tie-break (earliest lap wins), soft-deleted-server exclusion, `excludeLapId`'s before/after behavior, server-scoped Server Score, and the `sum`/`average` config variant switch.
- `MostActiveServerTest.php` — the Activity Score formula, Valid Laps as distinct (player, map) participations rather than raw lap count (grinding one map doesn't inflate it), the 90-day base window, recency bonus tiers (only the highest applies), the final tie-break tier (most recent activity — the other three tiers proved impractical to construct via realistic Activity Score arithmetic; the ×20 map weight dwarfs the ×10 player weight, so a natural tie with unequal player counts doesn't fall out of simple test data), and soft-deleted-server exclusion.
- `RecordHistoryTest.php` — a map's first-ever lap counts as a record with no previous time, only a strictly-faster lap counts (ties don't break a record), per-map independence, soft-deleted-server exclusion, `recent()`'s ordering/windowing/limit, and `firstRecordFor()`.
- `HomeTest.php` — real Quick Stats counts, the highlight-selection fallback to Live Stats Snapshot alone when every other candidate is empty, the sandbagging-protection gate (a huge-looking "improvement" landing at a non-competitive rank is correctly excluded), the distinct-lap preference across Fastest Improvements' sub-picks, Achievements' first-record detection, and Most Active Server's exclusion of zero-activity servers.

`GLOBAL_RANKING_VARIANT`/`GLOBAL_RANKING_AVERAGE_CONFIDENCE_MAPS` are pinned in `phpunit.xml` (to `sum`/`2`) so the suite never silently inherits whatever a developer currently has set in their local `.env` for manual A/B comparison.

**Webhook/job pipeline now covered (2026-07-06, roadmap item 14)**: `LapSubmissionTest.php` (9 tests) covers `POST /api/v1/laps` end-to-end — server/player/map creation via a live-queried hostname, map-label derivation, the failed-query placeholder-name fallback, logging every attempt (not just PBs), the pivot-duplication fix (`syncWithoutDetaching`), split storage, the `LeaderboardUpdated` broadcast firing only on genuine improvements, leaderboard position/gap, and payload validation. The real UDP client is swapped for a fake via a `GameServerQuery` interface bound in the container per-test — no real socket ever opens. This test suite is also what caught a real SQLite/PDO bug (a bound parameter compared against an aggregate in `WHERE`/`HAVING` is silently ignored in this environment's bundled SQLite 3.34.1) — see [decisions.md](decisions.md) for the full writeup; worth remembering before writing similar aggregate-comparison SQL elsewhere.

**Still not covered**: most Livewire components' non-algorithmic display logic (the four calculators plus the webhook are where the real bug risk lives, so they were prioritized first).

**The test suite now has a real, migratable schema.** Until `ServerShow` was wired to real Eloquent data, none of the real tables (`servers`, `maps`, `players`, `lap_times`, etc.) had migrations at all — they exist for real in the dev/prod DB but were deliberately never migrated there (see [database.md](database.md)). That meant the in-memory SQLite test DB had no schema for them, which broke the moment a component ran a real query. Fixed with `create_*_table` migrations matching the real schema, meant to run in fresh environments only (CI, a new dev machine) — see [decisions.md](decisions.md) for the full story. `RoutesTest.php` now uses `LazilyRefreshDatabase` (per the convention below) and seeds a `Server::factory()->create(['id' => 1])`, `Map::factory()->create(['id' => 1])`, and (2026-07-06) `Player::factory()->create(['id' => 1])` in `beforeEach()` for the routes now wired to real data (`servers.show`, `servers.maps.show`, `players.show`) — extend this same pattern (seed via factory, keep the dataset's hardcoded id in sync) as more routes get wired for real. When a route starts requiring a new related model (e.g. `servers.maps.show` needing a `Map` in addition to the `Server` `servers.show` already needed), add it to the same shared `beforeEach()` rather than a per-test setup — the routes dataset assumes a consistent set of id=1 rows exists for every test in the file.

Two stock example tests remain untouched (`tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`) — harmless Laravel-installer boilerplate, left in place per the "don't delete tests without approval" rule rather than assumed safe to remove.

### PHPStan + Larastan — `composer analyse`

Config: `phpstan.neon`, level 5. Deliberately not higher yet — most of the codebase is still frontend-on-mock-data, and mock-data Livewire components return untyped arrays by design (see [coding-standards.md](coding-standards.md)); a stricter level would mostly flag that intentional choice rather than real issues. **Raise the level as real Eloquent models/typed data replace the mock arrays.**

**Every model relationship method needs a `@return` PHPDoc generic** (e.g. `/** @return BelongsToMany<Map, $this> */`), not just the native `BelongsToMany`/`HasMany`/`BelongsTo` return type — without it, Larastan can't resolve what a relation's collection actually contains, and flags every property access on the result as "undefined property on `Illuminate\Database\Eloquent\Model`." All relations in `app/Models/` follow this convention; keep it going for any new relation.

**Raw aggregate queries (`selectRaw`/`groupBy` for things like per-map `MIN(time)`) should drop to `->toBase()`** before `->get()`/`->pluck()` rather than staying on the Eloquent builder. Those rows aren't real model instances (they're aggregates with columns the model doesn't actually have, e.g. a `best` alias), and Larastan correctly flags accessing them as "undefined property" on the model — `->toBase()` returns plain `stdClass` rows instead, which is both what's actually happening and what stops PHPStan from complaining. See `ServerShow::mount()` for the pattern. Passes clean at 0 errors as of the first real-data page (`ServerShow`).

### Rector — `composer rector-dry` / `composer rector-fix`

Config: `rector.php`. Laravel 13 + PHP 8.3 rule sets (`LARAVEL_130`, `LARAVEL_CODE_QUALITY`, `LARAVEL_COLLECTION`, `LARAVEL_IF_HELPERS`) plus generic `CODE_QUALITY`. `HasLapDetailModal` trait is excluded (`withSkip`) since it relies on Livewire-specific dynamic property/computed-property conventions Rector might otherwise "clean up" incorrectly.

**Always dry-run first** (`composer rector-dry`) and review the diff before running `rector-fix` — it rewrites files in place. As of setup, dry-run proposes 16 files' worth of changes (mostly `declare(strict_types=1)` and PHP 8.3 `#[Override]` attributes) — these haven't been applied yet, pending a deliberate decision to do so (not part of "get the tooling in place").

### Pint — `vendor/bin/pint --format agent` (already a Boost convention)

Already documented in the Boost guidelines block in `CLAUDE.md` — run after modifying any PHP file. Not new, just noting it's part of the same "lint" composer script now (`composer lint` runs Pint then PHPStan; `composer check` runs lint + rector-dry + test).

### Semgrep — **configured, not installed**

`.semgrep.yml` at the repo root has custom rules for this codebase (unescaped Blade output, raw-SQL string interpolation, hardcoded-credential-like assignments, a reminder to validate/authorize Livewire `mount()` route params once they're real lookups). **This sandbox has no way to install the actual Semgrep CLI** — it ships via pip/Homebrew/Docker only, and this environment has no pip module, no sudo (so no `apt install python3-pip`), no Homebrew, and no Docker. The config is ready to run wherever one of those is available:

```bash
# once semgrep is installed (pip install semgrep / brew install semgrep):
semgrep --config=.semgrep.yml --config=p/php --config=p/security-audit .
```

The custom rules haven't been validated against a real Semgrep run (couldn't run it here) — treat them as a starting point to sanity-check, not a proven-working ruleset, the first time someone actually runs this.

## Plan (unchanged from before tooling setup)

Per the original stack decision, write **Pest tests alongside each piece as it's built**, not as a separate pass at the end. Priority order once real backend work starts:

1. **Feature tests per route** — extend `RoutesTest.php` with real-data assertions once models exist (replacing the current all-mock-data-so-any-id-works assumption).
2. **Livewire component tests** — especially `HasLapDetailModal` behavior once it's driven by real data, and the nested-vs-global leaderboard scoping logic.
3. ~~**Webhook/job tests**~~ — **done (2026-07-06)**, see `LapSubmissionTest.php` above.
4. **Browser tests** (Pest 4) for interaction-heavy things — the Lap Detail modal's Alpine transitions, the mobile nav overlay, the split-pace sparkline's hover tooltips.

## Conventions

Follow the Boost-provided `pest-testing` skill and `laravel-best-practices`:
- `LazilyRefreshDatabase` over `RefreshDatabase` for speed.
- Factories for all model creation in tests — build factories/states as models are created, not after.
- `assertModelExists()` / `assertSuccessful()` over raw `assertDatabaseHas()` / `assertStatus(200)`.
- Use fakes (`Event::fake()`, etc.) **after** factory setup, not before.
- Dataset pattern (see `RoutesTest.php`) for repetitive per-route/per-input tests instead of one test method per case.
