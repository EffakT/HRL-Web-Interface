# Deployment

## Current environment

- Dev/staging is served at this app's hosting-account domain, pointed at the same filesystem and MySQL DB (`redesign_hrl`) used for local `php artisan serve` testing — changes made locally are visible there without a separate deploy step in the current setup.
- Production cutover target (eventual): replace the existing production app.

## Source control

- The Laravel application directory itself is the Git root; the surrounding hosting-account directory is deliberately excluded.
- The local repository uses `main` as its initial branch.
- GitHub remote: `git@github.com:EffakT/HRL-Web-Interface-v2.git`, configured locally as `origin`. The remote currently exists as an empty **public** repository.
- The dedicated repository-scoped deploy key at `../../.ssh/hrl_web_interface_v2` (outside this Git root, in the hosting account's home directory) is authorized in GitHub with write access. This repository's local `core.sshCommand` is pinned to that key with `IdentitiesOnly=yes`; `git ls-remote origin` succeeds.
- Repository-local commit identity is `EffakT <dusaro123@gmail.com>`.
- The repository deliberately has no license for now. Because it is public, its contents are visible but do not carry an explicit open-source grant unless a license is added later.

## Build process

Frontend assets are compiled via Vite: `npm run build` (production) or `npm run dev`/`composer run dev` (local watching). **Any frontend change requires a rebuild** to be visible — if a change doesn't show up, check this first before debugging further. This includes `VITE_REVERB_*` env vars (see below) — they're baked in at build time, so changing one needs a rebuild too, not just a config cache clear.

## Live updates (roadmap item 16) — three long-running processes required

Real-time leaderboard updates (see [database.md](database.md)'s "Live leaderboard updates" section) depend on **three** separate long-running processes, all already part of `composer run dev` for local work — a production deploy needs equivalents for all three, not just the app server:

1. **Reverb** (`php artisan reverb:start`) — the actual WebSocket server. Binds to `REVERB_SERVER_PORT` (defaults to `REVERB_PORT`'s value if unset). **This environment's default port 8080 was already taken by something else on the shared host** — moved to 8081 here (`REVERB_SERVER_PORT=8081` in `.env`). Check for a conflict before assuming the default port is free anywhere else this app runs. **Resolved (2026-07-08, OPS-01): now supervisor-managed**, see below.
2. **A queue worker** (`php artisan queue:work` or `queue:listen`) — broadcasting (`ShouldBroadcast`, not `ShouldBroadcastNow`) goes through the queue, not synchronously. Without a worker running, `LeaderboardUpdated` events queue up in the `jobs` table and never reach a browser — confirmed by testing this directly (see decisions.md).
3. **Vite build with the correct `VITE_REVERB_*` values** — the frontend's `resources/js/echo.js` reads these at build time to know which host/port/scheme to connect to.

**`REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME` vs. `VITE_REVERB_*` are deliberately different values (fixed 2026-07-07, see REL-01 in the audit history)**: the former drive server-side publishing (Laravel/the queue worker talking to Reverb over loopback — `localhost:8081`, plain `http`, correct and efficient as-is) while the latter are baked into the *public* JS bundle and must be a real, TLS-reachable hostname (this app's real public domain, port `443`, `https`) — a real browser reads `localhost` as its own machine, not the server, so aliasing one to the other (the original config) silently broke real-time updates for every visitor.

**Resolved (2026-07-07): the public WebSocket proxy is in place.** The actual topology in front of this app is **Nginx Proxy Manager (NPM) → FastPanel's nginx (this container, on its internal LAN IP) → PHP-FPM socket** — not FastPanel's nginx directly facing the internet, as first assumed. Reverb speaks the Pusher protocol, so the client connects to `wss://<this app's domain>/app/{REVERB_APP_KEY}`. Rather than editing FastPanel's per-site vhost (not readable/writable from inside this app's environment — no permission to the FastPanel per-site vhost directory), the fix was added one layer up, in NPM: a **Custom Location** for `/app` forwarding directly to this container's internal LAN IP on the Reverb port (bypassing FastPanel's nginx for that path), plus NPM's **Websockets Support** toggle enabled on the proxy host. Verified with a real WebSocket-upgrade `curl` returning `101 Switching Protocols` (both from inside this environment and via the user's own check).

## Process management (OPS-01, resolved 2026-07-08)

Reverb, the queue worker, and the scheduler are all supervisor-managed now, via conf files in `../../supervisord/` (a sibling directory outside this Git root, in the hosting account's home directory) — this host's supervisord includes each hosting account's `supervisord/` directory directly (see the `[include]` glob in the host's `supervisord.conf`), so no copy into the system-wide supervisor conf directory is needed, just `supervisorctl reread && supervisorctl update` after adding/editing a conf (this app's own user can't run `supervisorctl` — the host owner does the reload).

- `redesign-hrl-laravel-worker.conf` — `queue:work`, 8 processes (pre-existing).
- `redesign-hrl-reverb.conf` — `reverb:start`, 1 process.
- `redesign-hrl-schedule.conf` — `schedule:work` (not a cron entry — `schedule:work` is a long-running process that internally loops and fires due tasks every minute, which fits supervisor's process model better than requiring a crontab entry this app's user may not have access to add). Replaces the crontab approach described below, which was never actually set up.

All three run as this app's hosting-account user, logging to `../../logs/` (also outside this Git root). Verified 2026-07-08: `schedule:work`'s log shows `RefreshLiveServerInfo` actually firing every minute, and a raw WebSocket-upgrade `curl` against `localhost:8081` returned a real `101 Switching Protocols` with `X-Powered-By: Laravel Reverb` from the supervisor-managed process. One rollout snag hit and fixed: the pre-existing bare `reverb:start --debug` process from the OPS-01-open era (started 2026-07-07, no supervisor unit) was still holding port 8081, so supervisor's own Reverb process crash-looped on `EADDRINUSE` until that old PID was killed by hand.

### `schedule:work` stalled silently for ~23 hours (2026-07-10 incident)

Found live, not in a test: a real server's Servers List entry showed offline despite the game server actually being up and reachable (confirmed by running `app:refresh-live-server-info` manually — it succeeded instantly). Root cause: a `withoutOverlapping()` scheduler mutex lock (`cache_locks` table, key `framework/schedule-{hash}`) got orphaned — most likely from a previous `schedule:run` subprocess dying abruptly mid-tick — and every subsequent per-minute attempt silently failed to acquire it, so `schedule:work`'s log showed "No scheduled commands are ready to run" on every single tick for ~23 hours, with no error anywhere. **Killing and restarting the `schedule:work` process did *not* fix it** — the lock lives in the database, not the process, so a fresh process hits the exact same stuck row. Only deleting the stale `cache_locks` row unblocked it (confirmed live: the very next tick after deletion updated the affected server's `queried_at` automatically).

Fixed two ways:
1. **Removed `->withoutOverlapping()` from the schedule entry** (`routes/console.php`) — this job just re-queries a handful of servers over UDP (idempotent, ~2s each), so a genuinely overlapping run is harmless. Removing the mutex removes this entire failure class rather than trying to make the lock more robust.
2. **Added `App\Console\Commands\CheckSchedulerHealth`** (`app:check-scheduler-health`) — a deliberately independent watchdog that alerts (via `Log::critical()`) if no server's `queried_at` has updated within 10 minutes. Registered via a **real OS crontab entry**, not `Schedule::` — if `schedule:work`/Laravel's own scheduler is the thing that's stuck or dead, a check that also depends on it running would never fire either:
   ```
   */5 * * * * cd /var/www/redesign_hrl_usr54/data/www/redesign.hrl.effakt.info && /opt/php84/bin/php artisan app:check-scheduler-health >> /var/www/redesign_hrl_usr54/data/logs/scheduler-health.log 2>&1
   ```
   This app's user *does* have crontab access (confirmed live 2026-07-10) — the "no crontab" limitation noted below for the main scheduler was never re-checked after that assumption was first made 2026-07-08; a plain `crontab -l`/`crontab <file>` worked without any permission issue. Deliberately alerts only, not self-healing: this environment's user doesn't have `supervisorctl` access to restart `schedule:work` (confirmed 2026-07-10, `PermissionError`), and automatically deleting `cache_locks` rows on a schedule is a sharper tool than an unattended health check should wield.

A real bug was also caught building the watchdog: `now()->diffInMinutes($pastTimestamp)` returned a **negative** number in testing — Carbon 3 (this app's Laravel 13 dependency) made `diffInX()` methods signed by default (a past timestamp is "behind" now, hence negative), unlike Carbon 2's default absolute value. Fixed with the explicit `absolute: true` argument; worth remembering before writing similar diff comparisons elsewhere in this codebase.

## Cutover plan (from original planning, not yet executed)

1. Validate the new app fully in dev against the imported DB copy.
2. Point the new app at the real production DB (same schema, already compatible since we build additively against it).
3. Deploy.
4. Retire the old Vue 2 app.

Specifics (staging environment details, DNS/cutover timing) are **not yet decided** — see [roadmap.md](roadmap.md).

## Known gotchas

- **Missing `cache` table**: if `CACHE_STORE=database` in `.env` and the `cache`/`cache_locks` tables don't exist in the target DB, every Livewire interaction fatal-errors. Run only the cache-table migration if this happens on a fresh environment: `php artisan migrate --path=database/migrations/0001_01_01_000001_create_cache_table.php`. **Do not** run a full `php artisan migrate` against a DB that already has real `users`/`jobs`/etc. tables — the stock scaffold migrations will conflict. See [decisions.md](decisions.md).
- Never point Laravel Boost (or any AI tooling) at production DB credentials — dev/staging only, ideally a read-only user (not yet confirmed as actually read-only — see [roadmap.md](roadmap.md)).
- **Laravel's scheduler needs something to invoke it — it doesn't run itself.** `routes/console.php` registers `App\Console\Commands\RefreshLiveServerInfo` (roadmap item 19) via `Schedule::command(...)->everyMinute()`. **Resolved (2026-07-08)**: rather than a crontab entry (this app's user had none set up — `crontab -l` → "no crontab", and adding one wasn't confirmed straightforward given the hosting setup at the time), it's supervisor-managed via `schedule:work` — see "Process management (OPS-01)" above. The scheduler itself still decides which registered tasks are actually due; `schedule:work`'s internal loop just replaces the cron trigger. **Correction (2026-07-10)**: this app's user does in fact have working crontab access — re-confirmed live while adding the scheduler-health watchdog below, a plain `crontab -l`/`crontab <file>` just worked. The original "no crontab" finding was accurate at the time but never re-checked since; `schedule:work` under supervisor remains the right choice for the main per-minute job regardless (a long-running process fits that cadence better than cron), but crontab is a real, available option for anything that needs to run independently of it — see the incident write-up above.
