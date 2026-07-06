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
- ~~**Webhook authentication**~~ — **superseded (2026-07-07), see below.** The 2026-07-06 decision to keep the webhook open (matching the old app) was reversed after a site audit flagged it as SEC-01/critical: an unauthenticated caller could fabricate leaderboard data outright.

### SEC-01 — HRL query verification (added 2026-07-07)

The webhook can't use TLS/HMAC on the game-server side — the Lua/SAPP script that submits laps isn't part of this repo and isn't known to have that capability. Instead, `App\Helpers\LapSubmissionVerifier` (used by `LapSubmissionController::store()`) cross-checks every submission against a **live UDP `\query` response from the same ip:port the HTTP request came from**, reusing the protocol already built for roadmap item 19 (`App\Helpers\QueryServer`, `App\Models\Server`'s live-query fields). This binds the HTTP submission to a game server that is actually running an HRL-aware script, currently on the submitted map, with the submitting player online — without needing any cryptography on the Lua side.

The Lua script must publish these query_add fields (SAPP's existing `query_add <key> <value>` capability) alongside the standard ones (`hostname`, `mapname`, `player_0..N`, etc.):

```text
hrl_enabled = 1
hrl_protocol = 1
hrl_token = <short-lived random value, rotated periodically>
```

...and include the same `hrl_token` value as an `hrl_token` field in the HTTP submission body. `LapSubmissionVerifier::verify()` then requires, in order: a successful UDP query (one retry on failure), `hrl_enabled === '1'`, a supported `hrl_protocol` (config `webhook.hrl_query.supported_protocol`), a matching `hrl_token`, `mapname` matching the submitted `map_name`, and the submitted `player_name` appearing among the response's `player_0`/`player_1`/... values. Any failure returns a structured reason (`udp_timeout`, `missing_hrl_marker`, `protocol_unsupported`, `token_mismatch`, `map_mismatch`, `player_not_online`) that's logged.

**Rollout note — `enforce` defaults to `false`** (`config/webhook.php`, env `WEBHOOK_HRL_VERIFY_ENFORCE`): a failed verification is logged but the lap is still recorded. This is deliberate, not a bug — real game servers are still running Lua scripts written before this feature existed, and none of them publish `hrl_*` fields yet. Flipping `enforce` to `true` before every active server's script is updated would silently reject every legitimate submission. Turn it on only once the rollout is confirmed complete (watch the warning logs for `missing_hrl_marker` from known-good servers dropping to zero).

**What this doesn't protect against**: a server operator controls their own query responses and could fabricate activity *on their own server* (they already could, by running a real client and legitimately racing). An on-path attacker between this app and a legitimate server could still observe or alter plaintext HTTP/UDP traffic — this is verification of "a live HRL-aware game server is actually there," not encryption or non-repudiation. The audit's alternative (a local TLS-terminating relay per game-server host, HMAC/mTLS) would close that gap but requires real infrastructure work outside this repo on every game-server host; not pursued here.

A short-window idempotency guard (`Cache::add`, `config('webhook.duplicate_window_seconds')`, default 10s) runs independently of HRL verification — see "Follow-up hardening" below for how this evolved from a plain duplicate-rejecting guard into a real idempotent-replay mechanism.

#### Follow-up hardening (2026-07-07, same day)

A second review of commit `0f2362a`'s implementation (recorded directly in `SITE_AUDIT.md`) found five real gaps before `enforce` could ever safely flip to `true`. All five are fixed in the same day's follow-up commit — see [decisions.md](decisions.md) for the full writeup:

1. **Rate-limit bypass via port rotation** — the webhook limiter's only key was `ip:port`, and `port` is caller-supplied and unverified at the rate-limit layer, so an attacker could rotate it on every request for a fresh 120/min allowance each time (while still forcing a real UDP query attempt per request). Fixed: `RateLimiter::for('webhook', ...)` now returns **two** limits that both apply — the existing per-`ip:port` limit, plus a coarser per-IP ceiling (`config('webhook.rate_limit.per_ip_per_minute')`, default 600/min) that port rotation can't evade.
2. **No token rotation grace or replay protection beyond exact-value dedup** — `LapSubmissionVerifier` now also accepts an optional `hrl_token_prev` query field (the Lua script's previous token, if it rotates them), so a submission racing a rotation boundary isn't wrongly rejected. Real replay protection is handled by point 3's idempotency key, not by the token itself — see the class docblock for why the token isn't meant to be a durable secret.
3. **Retry poisoning — a legitimate retry got an error, not its original result.** The original `Cache::add()` guard reserved the dedupe key *before* verification/processing; if a legitimate retry arrived (e.g. the client never saw its own successful HTTP response), it got a bare `409 duplicate_submission` instead of the original success payload, and a pre-commit failure left the reservation stuck for the rest of the window even though nothing was recorded. Fixed: the cache now tracks a real state machine (`processing` → `done`, storing the actual response body/status) — a repeat of the same key while `done` replays the original response verbatim; a `Throwable` during processing releases (`Cache::forget`) the reservation instead of leaving it stuck. Also accepts an optional client-generated `submission_id` field as the idempotency key when present, falling back to the previous content-hash approach otherwise.
4. **Duplicate UDP query on every accepted submission** — `LapSubmissionVerifier::verify()` already performs a live UDP query; `ProcessNewLap::resolveHostname()` was doing a second, independent one for the exact same ip:port. Fixed: `verify()` now returns the raw `response` array, and `LapSubmissionController` passes it into `ProcessNewLap`'s new `$liveQueryResponse` constructor parameter — `resolveHostname()` reuses it instead of querying again. (When verification is disabled, or its own query failed, `$liveQueryResponse` is `null` and `ProcessNewLap` falls back to its own query, unchanged from before.)
5. **Loose player-field matching** — `player_not_online` matched any query key merely *starting with* `player_`, which a future query extension (e.g. a hypothetical `player_count`) could accidentally satisfy. Fixed: matches only `/^player_\d+$/`.

**Still open, deliberately not built**: a numeric token *maximum age* check. Doing that meaningfully needs the Lua script to publish an issue timestamp the server can compare against its own clock, which is more surface on the Lua side than the current/previous-token grace already implemented — revisit only if the current/previous fallback proves insufficient in practice.

#### Second follow-up hardening (2026-07-07, same day, commit `eb28327` reviewed again)

A third audit pass reviewed `eb28327` and found five more real gaps — two of which involved genuine open-ended design decisions (exact rate-limit numbers, how much idempotency infrastructure to build), so those were confirmed with the user directly before implementing, rather than decided solo. Full technical detail below; reasoning in [decisions.md](decisions.md).

1. **Cross-server `submission_id` collision (High)** — when a client sends its own `submission_id`, the idempotency cache key was JUST that value, with no ip/port namespace. Two different game servers generating similar counters (e.g. both starting from 1) could collide and one would receive the other's cached response. Fixed: the idempotency key is now always `lap-submission:{ip}:{port}:{submissionKey}`, whether `submissionKey` is a real `submission_id` or the content-hash fallback. Also added a `min:8` validation rule on `submission_id` as a minimum-entropy floor.
2. **Idempotency wasn't durable (High)** — the cache is an optimization, not a guarantee; if it's ever lost (app restart, cache eviction, a very late retry past the retention window) nothing stopped a genuine duplicate lap from being recorded. **User's call, given two schema options**: a simple unique column on `lap_times` itself, rather than a separate claims table. Added a nullable `submission_id` string column + a unique index on `(server_id, submission_id)` (multiple NULLs allowed, so laps without one are unaffected) via a real migration. `ProcessNewLap::handle()` now catches the resulting `QueryException` on a genuine collision and replays a best-effort response built from the already-recorded lap's current state (not a fabricated success) — see `replayDuplicateSubmission()`.
3. **UDP exhaustion still practical at the initial 600/min per-IP ceiling (High)** — the audit calculated that forcing two 1-2s UDP timeouts per request could still occupy ~40 PHP workers from one source even under the reduced port-rotation-proof ceiling. **User's call, given two options**: build a two-tier system, not just lower the flat number. A source starts in a strict "unverified" tier (30/min per IP, 15/min per ip:port, burst 2/sec) and only earns a much more generous "verified" tier (300/min, 120/min, burst 10/sec) after a request from that exact ip:port has actually passed HRL query verification — cached at `LapSubmissionVerifier::verifiedMarkerKey()` for 5 minutes. Verification still runs on every single request regardless of tier; the marker only ever changes how much traffic is allowed. The coarse per-IP ceiling applies at both tiers specifically so running many ports can't bypass it even once verified. Also reduced `hrl_query.timeout_seconds` from 2 to 1. **Not implemented**: nginx/PHP-FPM concurrency/timeout limits — that's deployment/infra configuration outside this Laravel repo, flagged as a prerequisite rather than something this codebase can enforce.
4. **10-second reservation/result window was too short for one purpose and too long for another (Medium)** — a single shared TTL couldn't simultaneously (a) outlast a slow in-flight request without letting concurrent duplicates through, and (b) outlast how long a real client might wait before retrying. Split into `processing_reservation_seconds` (30s — generous margin over the worst-case UDP+DB round trip) and `result_retention_seconds` (300s — "several minutes," per the audit).
5. **Duplicate UDP query on the failure path (Medium, a residual case of the first follow-up's point 4)** — when verification's own query totally failed (`udp_timeout`), `ProcessNewLap` would still attempt its own separate query against the same already-unresponsive ip:port. Fixed by giving `$liveQueryResponse` a real tri-state: `null` (verification didn't run, try normally), `false` (verification tried and got nothing, don't retry), or the actual response array.

New tests: `LapSubmissionVerifierTest.php` gained cases for `verifiedMarkerKey()` and the `false` response state; `LapSubmissionTest.php` gained cases for cross-server non-collision, the durable DB constraint surviving a cache flush, and the tiered rate limiter's upgrade behavior. 125 → 129 tests, 308 → 321 assertions.

- **Input validation** for real Eloquent-backed forms — none exist yet since everything is still mock data. The lap-submission webhook (2026-07-06) is the one exception so far — validated via `StoreLapTimeRequest`.

## Guidance going forward

Follow the Laravel Boost-provided `laravel-best-practices` skill's security section when writing real backend code: `$fillable`/`$guarded` on every model, authorize every action via policies/gates, no raw SQL with user input, `throttle` middleware on auth/API routes, validate file uploads by MIME/extension/size, `encrypted` cast for sensitive DB fields.

## Tooling (added 2026-07-05)

Static analysis is now in place to catch some of this automatically — see [testing.md](testing.md) for full detail:

- **PHPStan (Larastan)** (`composer analyse`) — level 5, catches type errors and some Laravel-specific misuse. Not a security scanner per se, but type safety reduces a class of bugs that become security issues (e.g. passing the wrong shape of data into a query).
- **Semgrep** (`.semgrep.yml`) — **configured but not installed** in this environment (no pip/Homebrew/Docker available here — see [testing.md](testing.md) for why). Custom rules cover unescaped Blade output, raw-SQL string interpolation, hardcoded-credential-like assignments, and a reminder to validate/authorize Livewire route params once they're real lookups. Run `semgrep --config=.semgrep.yml --config=p/php --config=p/security-audit .` wherever Semgrep can actually be installed (CI, or a local machine with pip/brew) — this hasn't been run for real yet, so treat the custom rules as unvalidated until someone does.
- **Rector** (`composer rector-dry`) — code-quality/modernization, not security-focused, but keeping the codebase on current Laravel/PHP idioms reduces the odds of copy-pasting a since-deprecated insecure pattern.

None of this replaces a real security review before launch — it's baseline hygiene tooling, run early specifically so gaps don't compound as real backend code gets written.
