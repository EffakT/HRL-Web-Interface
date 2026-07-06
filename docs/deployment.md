# Deployment

## Current environment

- Dev/staging is served at `redesign.hrl.effakt.info`, pointed at the same filesystem and MySQL DB (`redesign_hrl`) used for local `php artisan serve` testing — changes made locally are visible there without a separate deploy step in the current setup.
- Production cutover target (eventual): replace the old app at `https://hrl.effakt.info`.

## Source control

- The Laravel application directory itself is the Git root; the surrounding hosting-account directory is deliberately excluded.
- The local repository uses `main` as its initial branch.
- GitHub remote: `git@github.com:EffakT/HRL-Web-Interface-v2.git`, configured locally as `origin`. The remote currently exists as an empty **public** repository.
- The dedicated repository-scoped deploy key at `/var/www/redesign_hrl_usr54/data/.ssh/hrl_web_interface_v2` is authorized in GitHub with write access. This repository's local `core.sshCommand` is pinned to that key with `IdentitiesOnly=yes`; `git ls-remote origin` succeeds.
- Repository-local commit identity is `EffakT <dusaro123@gmail.com>`.
- The repository deliberately has no license for now. Because it is public, its contents are visible but do not carry an explicit open-source grant unless a license is added later.

## Build process

Frontend assets are compiled via Vite: `npm run build` (production) or `npm run dev`/`composer run dev` (local watching). **Any frontend change requires a rebuild** to be visible — if a change doesn't show up, check this first before debugging further.

## Cutover plan (from original planning, not yet executed)

1. Validate the new app fully in dev against the imported DB copy.
2. Point the new app at the real production DB (same schema, already compatible since we build additively against it).
3. Deploy.
4. Retire the old Vue 2 app.

Specifics (staging environment details, DNS/cutover timing) are **not yet decided** — see [roadmap.md](roadmap.md).

## Known gotchas

- **Missing `cache` table**: if `CACHE_STORE=database` in `.env` and the `cache`/`cache_locks` tables don't exist in the target DB, every Livewire interaction fatal-errors. Run only the cache-table migration if this happens on a fresh environment: `php artisan migrate --path=database/migrations/0001_01_01_000001_create_cache_table.php`. **Do not** run a full `php artisan migrate` against a DB that already has real `users`/`jobs`/etc. tables — the stock scaffold migrations will conflict. See [decisions.md](decisions.md).
- Never point Laravel Boost (or any AI tooling) at production DB credentials — dev/staging only, ideally a read-only user (not yet confirmed as actually read-only — see [roadmap.md](roadmap.md)).
- **Laravel's scheduler needs a real cron entry — it doesn't run itself.** `routes/console.php` registers `App\Console\Commands\RefreshLiveServerInfo` (roadmap item 19) via `Schedule::command(...)->everyMinute()`, but that's inert until something actually invokes `php artisan schedule:run` on a cadence. **Confirmed missing in this dev environment** (2026-07-06, `crontab -l` → "no crontab"): without it, live server info (current map/online status on Servers List) only updates when the command is run manually. Required crontab line once ready to enable it for real:
  ```
  * * * * * cd /var/www/redesign_hrl_usr54/data/www/redesign.hrl.effakt.info && /var/www/redesign_hrl_usr54/data/bin/php artisan schedule:run >> /dev/null 2>&1
  ```
  Runs under the `redesign_hrl_usr54` user (no `www-data`/root needed here) — every minute is the cron trigger, not the actual task interval; the scheduler itself decides which registered tasks are due. Worth remembering for any *future* scheduled task too (e.g. if item 16's Reverb/Echo work or anything else ever needs one) — they all share this same single cron entry, nothing per-task.
