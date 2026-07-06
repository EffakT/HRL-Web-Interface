# Security

No formal security review has been done yet. Phase 2 real-data integration is underway, but write endpoints and authentication are not built. This doc tracks what's decided, what Laravel/Livewire give for free, and what's still open.

## Secrets & credentials

- DB credentials, API tokens, etc. live in `.env` — never commit it, never hand it to an AI agent or third party directly.
- The Git repository was initialized only after verifying `.gitignore` excludes `.env`, dependency directories, compiled frontend assets, and Laravel key files. Re-check the staged file set before the first commit and push.
- The configured GitHub repository is public. No push has occurred yet; the staged index must pass a secret-file/content review before the initial commit. GitHub access should be provided via an account SSH key or repository-scoped deploy key, never by placing a personal access token in project files or chat transcripts.
- A dedicated Ed25519 deploy key was generated outside the application Git root at `/var/www/redesign_hrl_usr54/data/.ssh/hrl_web_interface_v2`, and this repository alone is configured to use it. Only the `.pub` half belongs in GitHub; the private half remains server-local and must never be committed or pasted elsewhere.
- **Open item**: confirm the dev DB user has read-only or otherwise scoped privileges, as originally intended. Not yet verified — see [roadmap.md](roadmap.md).
- Laravel Boost (AI tooling) should only ever be pointed at the dev DB copy, never prod credentials.

## Framework defaults already in place

- CSRF protection on all state-changing routes (Laravel default middleware).
- Blade `{{ }}` output escaping used throughout — no raw `{!! !!}` in the codebase currently.
- Livewire's own checksum/rate-limiting on component actions (this is what depends on the `cache` table — see [decisions.md](decisions.md) for the incident where a missing `cache` table caused every `wire:click` to fatal-error).

## Known issues to fix from the old app

- Old API's Player/Server resources have a JSON key bug: `"name "` with a trailing space instead of `"name"`. Fix while rebuilding the API — see [api.md](api.md).
- Old API requires a per-user token generated in profile; auth/token strategy for the new API isn't decided yet.

## Not yet addressed

- **Auth** (`/login`, `/register`) — not built. No password hashing/session strategy beyond Laravel defaults has been exercised yet.
- **Authorization** — no policies/gates exist yet since no models/auth exist. Once the claim-code ownership system (`users_players`/`users_servers`) is revisited, ownership-based authorization will need real policies (server/player owners being allowed to, e.g., delete their own laps — see [roadmap.md](roadmap.md) "Future Plans" ported from the old site).
- ~~**Rate limiting** on the future public API~~ — **done (2026-07-06)**: 60/min per IP via Laravel's `throttle:api` for the read endpoints, 120/min per IP via its own `webhook` limiter for `POST /api/v1/laps`, see [api.md](api.md).
- ~~**Webhook authentication**~~ — **confirmed and decided (2026-07-06)**: the old webhook had no authentication mechanism at all — any IP could POST fabricated laps. Rather than port this silently, it was surfaced to the user explicitly before the rebuild (add a shared-secret header vs. keep it open). **Decision: keep it open**, matching the old app. This remains a real gap — anyone who finds the endpoint can inject fake leaderboard entries — but it's now a deliberate, documented choice rather than an unexamined port. Revisit if abuse actually happens; see [database.md](database.md) and [decisions.md](decisions.md).
- **Input validation** for real Eloquent-backed forms — none exist yet since everything is still mock data. The lap-submission webhook (2026-07-06) is the one exception so far — validated via `StoreLapTimeRequest`.

## Guidance going forward

Follow the Laravel Boost-provided `laravel-best-practices` skill's security section when writing real backend code: `$fillable`/`$guarded` on every model, authorize every action via policies/gates, no raw SQL with user input, `throttle` middleware on auth/API routes, validate file uploads by MIME/extension/size, `encrypted` cast for sensitive DB fields.

## Tooling (added 2026-07-05)

Static analysis is now in place to catch some of this automatically — see [testing.md](testing.md) for full detail:

- **PHPStan (Larastan)** (`composer analyse`) — level 5, catches type errors and some Laravel-specific misuse. Not a security scanner per se, but type safety reduces a class of bugs that become security issues (e.g. passing the wrong shape of data into a query).
- **Semgrep** (`.semgrep.yml`) — **configured but not installed** in this environment (no pip/Homebrew/Docker available here — see [testing.md](testing.md) for why). Custom rules cover unescaped Blade output, raw-SQL string interpolation, hardcoded-credential-like assignments, and a reminder to validate/authorize Livewire route params once they're real lookups. Run `semgrep --config=.semgrep.yml --config=p/php --config=p/security-audit .` wherever Semgrep can actually be installed (CI, or a local machine with pip/brew) — this hasn't been run for real yet, so treat the custom rules as unvalidated until someone does.
- **Rector** (`composer rector-dry`) — code-quality/modernization, not security-focused, but keeping the codebase on current Laravel/PHP idioms reduces the odds of copy-pasting a since-deprecated insecure pattern.

None of this replaces a real security review before launch — it's baseline hygiene tooling, run early specifically so gaps don't compound as real backend code gets written.
