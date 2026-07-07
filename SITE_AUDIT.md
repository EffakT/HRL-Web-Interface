# Site Audit — Halo Race Leaderboard Redesign

**Audit date:** 7 July 2026 (Pacific/Auckland)  
**Scope:** repository, deployed site, Laravel runtime configuration, dependencies, automated tests, static analysis, database indexes/privileges, response headers, representative route timing, accessibility/SEO markup, and recent logs.  
**Constraint:** audit only. No application code or configuration was changed.

## Executive summary

**Overall status: not production-ready.**

The application logic has a strong automated baseline. At the latest follow-up, all **141 Pest tests / 355 assertions passed**, PHPStan reported **0 errors**, Pint passed, PHP syntax checks passed, and both locked PHP and npm dependencies had **no known security advisories** at audit time.

The largest risks are outside those passing checks:

1. The deployed site serves plain HTTP without redirecting to HTTPS and has no HSTS. The original local/debug configuration was corrected after the audit.
2. The production frontend bundle connects Reverb to `localhost:8081` over non-TLS WebSockets. Public real-time updates therefore cannot work correctly and may be blocked as mixed content.
3. The home page consistently takes **1.80–1.97 seconds TTFB** with only 1,657 laps. Its implementation repeatedly recalculates full leaderboard/history data and will degrade as data grows.
4. Core security headers, durable asset caching, CI enforcement, browser tests, and several abuse-case tests are absent.

## Prioritised findings

| ID | Severity | Finding |
|---|---:|---|
| SEC-01 | Resolved | Live UDP server verification implemented and staged; updated Lua is a production-cutover requirement |
| SEC-02 | Mitigated | HTTPS redirect + HSTS added for web routes (API stays HTTP for legacy clients); nginx-side HTTPS detection not yet verified |
| SEC-03 | Resolved | Runtime changed to `staging`, debug off, with the correct HTTPS URL |
| REL-01 | Mitigated | Client now built with the real public wss hostname and Reverb is running; nginx WebSocket proxy still missing (blocks it end-to-end) |
| PERF-01 | High | Home page TTFB is ~1.8–2.0s at very small data volume |
| SEC-04 | High | Webhook payload permits unbounded split rows and weak numeric bounds |
| SEC-05 | Medium | Security headers are largely absent |
| SEC-06 | Medium | Reverb allows every origin and has connection rate limiting disabled |
| SEC-07 | Medium | Local secret-file permissions and DB privileges exceed least privilege |
| PERF-02 | Medium | Important lookup and aggregate indexes are missing |
| PERF-03 | Medium | Players and leaderboard endpoints compute/load complete result sets |
| PERF-04 | Medium | Hashed assets lack explicit long-lived immutable caching |
| TEST-01 | Medium | Security, rate-limit, browser, WebSocket, and production-path test gaps |
| OPS-01 | Medium | Queue/Reverb/scheduler process management is not defined for this deployment |
| A11Y-01 | Medium | Interactive podium cards and modals have keyboard/focus deficiencies |
| SEO-01 | Low | Minimal metadata and no sitemap/canonical/social metadata |
| QUAL-01 | Low | Rector dry-run and documentation are not at a clean current baseline |

## Security

### SEC-01 — Unauthenticated public write endpoint (Resolved)

`POST /api/v1/laps` intentionally has no authentication. The only control is a per-IP limit of 120 requests/minute.

An arbitrary caller can:

- create a server identity from its source IP and supplied port;
- create arbitrary map names and display entries;
- create players from arbitrary names/hashes;
- append unlimited lap attempts to permanent history;
- alter rankings and activity highlights;
- trigger site-wide queued broadcasts.

This is a direct integrity failure for the site's primary data. Rate limiting reduces request frequency but does not establish that the sender is a trusted Halo server.

**Recommended mitigation under the game-server constraints:** verify every HTTP submission against the source game server's live UDP query response before creating data. The Lua script can use its existing `query_add <key><value>` capability to publish HRL-specific query fields without needing TLS or cryptographic support.

Suggested query fields:

```text
hrl_enabled = 1
hrl_protocol = 1
hrl_token = <short-lived random value>
```

The Lua submission should include the same `hrl_token` in its HTTP payload. Laravel should then perform the following checks before opening the database transaction:

1. Take the HTTP request's real source IP and submitted game-server port; never accept a separate caller-supplied IP.
2. UDP-query that exact IP and port, using a short timeout and one retry.
3. Require `hrl_enabled=1` and a supported `hrl_protocol` value.
4. Require the UDP `hrl_token` to match the token in the HTTP submission.
5. Require the UDP `mapname` to match the submitted map.
6. Require the submitted player name to appear in the UDP `player_0`, `player_1`, etc. values.
7. Only after all checks pass, auto-create or update the server and record the lap. Use the UDP `hostname` as the server display name.

The query token should rotate periodically or after submissions where practical. It provides freshness and binds the HTTP submission to a server currently running the HRL script; it must not be treated as a durable secret because UDP query values are publicly readable. Avoid embedding one global static API secret in the distributed Lua script.

Failed or ambiguous verification should fail closed or enter a short-lived quarantine for retry—not immediately affect rankings. Record structured rejection reasons for UDP timeout, missing HRL marker, token mismatch, map mismatch, and player-not-online. Rate-limit by the verified IP/port identity, retain strict payload limits, and add short-window duplicate-payload detection to reduce replay.

This is a meaningful mitigation, not full cryptographic authentication. A server operator can modify or emulate client-controlled Lua/query responses and can therefore fabricate activity on their own server. An on-path attacker can still observe or alter plaintext HTTP/UDP traffic. The design does, however, prevent an ordinary web caller from auto-creating a server or submitting a counted lap unless the same source IP and port also presents a live, matching HRL-enabled game server with that map and player online.

#### Implementation follow-up — 7 July 2026

Commit `0f2362a` implements the basic UDP verification flow in `App\Helpers\LapSubmissionVerifier`: it checks the HRL marker and protocol, token equality, map equality, and the online player list, with one UDP retry. The direction is sound, and its new tests pass. **SEC-01 remains open**, however, because the deployed effective configuration has `webhook.hrl_query.enforce=false`; failed verification is currently logged but the lap is still recorded.

The following issues should be resolved before enabling enforcement:

1. **Rate-limit bypass / UDP exhaustion (High):** the webhook limiter's only key is `request IP + caller-supplied port`. An attacker can rotate valid port numbers to obtain a fresh 120-request allowance for each one while forcing up to two UDP timeouts per request. Apply both a coarse source-IP ceiling and a narrower IP/port limit; the latter must not replace the former.
2. **Token freshness and replay (Medium):** the implementation checks only one `hrl_token` for equality. It has no issued time, current/previous-token grace, maximum age, or unique submission ID. The backend cannot tell whether a token is short-lived, and the same payload can be accepted again after the 10-second duplicate window while the query token remains current. Implement the documented rotating current/previous-token scheme and an idempotent per-lap submission ID retained for at least the full replay window.
3. **Retry poisoning / non-idempotent duplicate response (Medium):** `Cache::add()` reserves the duplicate key before UDP verification and processing. Verification or database failure leaves that key active, so a legitimate retry receives `409`; a retry after an accepted lap also receives an error rather than the original successful result. Reserve/process atomically, release reservations on pre-commit failure, and store/return the original result for repeated submission IDs.
4. **Duplicate UDP work (Medium):** an accepted request performs one UDP lookup in the verifier and then `ProcessNewLap` performs another lookup for the hostname. Reuse the already-verified query response. In the current log-only rollout, an invalid request can incur two verifier timeouts plus the job's third query before still being stored.
5. **Player-field matching (Low):** restrict online-player keys to the exact `player_<number>` pattern rather than every key beginning with `player_`, preventing unrelated query extensions from being treated as player slots.

Do not set `WEBHOOK_HRL_VERIFY_ENFORCE=true` until the Lua rollout is complete and the rate-limit/retry issues above are fixed. Once enabled, verify with a real server submission, a deliberately invalid token, a player-not-online submission, a map mismatch, a token-rotation boundary, concurrent laps, and a retry after an intentionally lost HTTP response.

#### Second implementation follow-up — commit `eb28327`

The follow-up commit materially improves the first implementation: it applies coarse per-IP and narrower IP/port limits together, accepts the immediately previous query token during rotation, restricts player fields to `/^player_\d+$/`, replays cached terminal responses, releases reservations after caught failures, and reuses a successfully verified UDP response rather than querying again. The focused tests and full suite pass.

**SEC-01 nevertheless remains open.** This review found the following remaining issues:

1. **Cross-server idempotency collision (High):** when `submission_id` is present, the cache key contains only that caller-provided value. Different servers commonly generate similar counters or IDs; one server can therefore collide with another server's in-flight/completed submission and receive its response or lose its own lap. Always namespace or hash `submission_id` with the source IP and submitted port. Validate an explicit format and minimum entropy if IDs are intended to be random.
2. **Idempotency is not durable/transactional (High):** the cache state is separate from the lap transaction. `ProcessNewLap` commits the lap before leaderboard calculation and queued event dispatch. If either post-commit operation throws, the controller catches the exception, forgets the reservation, and a retry can insert a duplicate lap. A cache-write failure after successful processing has the same outcome. Store the submission ID durably with a unique database constraint and claim it in the same transaction as the lap; cached response replay can remain an optimisation, not the source of truth.
3. **UDP exhaustion remains practical at default limits (High):** port rotation no longer bypasses the limiter, but the default coarse ceiling is 600 requests/minute/IP. With two 2-second UDP timeouts, one source can sustain roughly 40 occupied PHP workers. During the current log-only rollout, a failed verifier can also leave `liveQueryResponse=null`, causing `ProcessNewLap` to perform a third UDP attempt before the invalid lap is still stored. Use a substantially lower ceiling and timeout for unverified traffic; consider separate allowances only after a source has verified successfully.
4. **Ten-second reservation/result lifetime is too short (Medium):** the processing reservation can expire while a slow request is still active, allowing a second request to enter concurrently. Completed results also expire before the proposed current-plus-previous token lifetime, so a later retry can insert another lap. Use a longer processing lock and retain completed IDs for several minutes at minimum—or rely on the durable unique submission ID above.
5. **Token age remains Lua-controlled (Residual/accepted only if explicit):** current/previous token equality gives useful live binding, but the backend does not enforce rotation or maximum age. A Lua script that never rotates its token leaves it valid indefinitely. This is acceptable only as a documented trust assumption; otherwise publish and validate an issued epoch or enforce token change observations server-side.

Do not consider cache-only idempotency sufficient for permanent lap history. Before enforcement, test cross-server duplicate IDs, reservation expiry during deliberately slow processing, an exception after the lap transaction commits, cache failure after commit, retries outside ten seconds, and the effective PHP-worker load from timed-out UDP verification.

#### Third implementation follow-up — commit `a9605d1`

This commit addresses most of the second review: the cache key is now scoped by source IP/port, processing and result TTLs are split into 30 and 300 seconds, `lap_times` has a deployed nullable `submission_id` with a unique `(server_id, submission_id)` index, successful verification earns a temporary higher rate-limit tier, the unverified tier is reduced to 30/IP/minute and 15/IP:port/minute with a 2/second burst, and UDP timeout is reduced to one second. The failed-query tri-state also prevents `ProcessNewLap` from performing a third UDP attempt after the verifier's two attempts fail.

Staging verification confirmed:

- migration batch 15 (`add_submission_id_to_lap_times_table`) has run;
- the production MySQL index `lap_times_server_id_submission_id_unique` exists and is unique;
- no duplicate `(servers.ip, servers.port)` identities currently exist;
- effective HRL enforcement remains off for rollout;
- 129 tests / 321 assertions, PHPStan, and Pint all pass.

**SEC-01 remains open pending these issues:**

1. **Verified-tier revocation (High):** successful verification creates a five-minute `verified-webhook-source:{ip}:{port}` marker, but a later verification failure does not remove it. A source can verify once, stop answering UDP, and retain the 300/minute tier for up to five minutes while forcing timeout work. Forget the marker on every failed verification; the current failing request may consume the verified allowance, but subsequent requests must immediately return to the strict tier.
2. **Server identity race (High):** durable lap idempotency is scoped by `server_id`, but `servers` still has no unique `(ip, port)` constraint. Concurrent first submissions for a new source can race through `firstOrCreate()` and create distinct server rows, fragmenting identity and weakening server-scoped guarantees. Existing data has no duplicates, so add the unique constraint after retaining the current preflight check.
3. **Over-broad duplicate exception handling (Medium):** `ProcessNewLap` treats SQLSTATE `23000` as a unique-key collision, but MySQL uses that class for other integrity failures, including foreign-key violations. With a non-null submission ID, an unrelated integrity error can be misreported as an idempotent duplicate and returned as HTTP 200. Catch Laravel's specific `UniqueConstraintViolationException` and/or verify the driver duplicate-key code/index before replaying.
4. **Idempotency-key payload conflict (Medium):** reusing a `submission_id` with changed lap fields silently returns the old result; an existing test explicitly permits this. Store a canonical request hash with the accepted submission and return `409 idempotency_conflict` when an ID is reused with different content. Once the Lua rollout is complete, require `submission_id` whenever HRL verification is enforced; otherwise accepted requests without it retain only cache-based duplicate protection.

#### Fourth implementation follow-up — commit `c529742`

This commit addresses the third review's four code findings. Failed UDP verification now immediately revokes the verified-tier marker; `submission_id` is required when HRL enforcement is enabled; cached entries store a content hash and return `409 idempotency_conflict` for changed content; unique violations use Laravel's specific `UniqueConstraintViolationException`; and active server identity is protected by a soft-delete-aware generated-column constraint.

The deployed MySQL schema was inspected directly and confirms:

- generated virtual column `servers.active_since = COALESCE(deleted_at, '2000-01-01 00:00:00')`;
- unique index `servers_ip_port_active_since_unique (ip, port, active_since)`;
- no duplicate active `(ip, port)` identities;
- active duplicates are rejected while an archived address can be reused.

The chosen generated timestamp is functional. Its documented residual edge is that two successive server records using the same IP/port cannot both be archived at the exact same second because their generated indexed timestamp would collide. The nullable generated active-slot design (`1` for active, `NULL` for archived) avoids that edge, but the deployed approach is acceptable if this rare administrative collision is explicitly retained as a known limitation.

**Remaining findings:**

1. **Durable payload-conflict detection is incomplete (Medium):** the accepted request's content hash exists only in the five-minute cache. After cache expiry, restart, or eviction, reusing the same `(server_id, submission_id)` with different content triggers the database unique constraint and `replayDuplicateSubmission()` returns the old lap as a success; it cannot detect that the new payload differs. Persist a canonical `submission_hash` with the lap and compare it during durable replay, returning `409 idempotency_conflict` on mismatch.
2. **Canonical content hash omits material fields (Medium):** the current hash includes player hash, map name, lap time, token, and splits, but omits at least `race_type` and `player_name`. Build the fingerprint from every validated, material submission field except `submission_id` itself. Define canonical ordering/normalisation for nested splits so equivalent payloads hash consistently.
3. **Server-race exception classification is implicit (Low):** after excluding the submission-ID index, `ProcessNewLap` assumes every other `UniqueConstraintViolationException` came from the server active-identity index and retries the transaction. That is true with today's schema but becomes unsafe if another unique constraint is added later. Explicitly check `servers_ip_port_active_since_unique`; rethrow unknown unique violations.

Operationally, HRL enforcement remains off during Lua rollout, so SEC-01 is not closed yet.

#### Fifth implementation follow-up — commit `aeef9e4`

This commit resolves the fourth review's three code findings:

- `lap_times.submission_hash` now persists the canonical payload fingerprint, and durable duplicate replay returns `409 idempotency_conflict` when the stored and incoming hashes differ after cache expiry, eviction, or restart;
- the shared `LapSubmissionHash` helper is used by both controller/cache and job/database paths, includes `race_type` and `player_name`, and sorts splits by checkpoint ID before hashing;
- unique violations are now explicitly classified as either the lap submission-ID index or the server active-identity index, with unknown unique violations rethrown.

Staging schema inspection confirmed that nullable `CHAR(64) submission_hash` is deployed alongside the existing unique `(server_id, submission_id)` index. The full suite passes at 137 tests / 349 assertions; PHPStan reports zero errors and Pint passes.

**Remaining narrow correctness item (Low):** `LapSubmissionHash` calls `json_encode()` on validation output without first coercing numeric fields to canonical types. Laravel's `numeric` and `integer` validation rules accept numeric strings but do not transform them, so semantically equivalent values such as `42.5` and `"42.5"`, or checkpoint `2` and `"2"`, can produce different hashes. Cast `race_type`/checkpoint IDs to integers and lap/split times to a defined numeric representation before sorting/hashing. Also reject duplicate checkpoint IDs so equal-key split ordering cannot remain payload-order-dependent.

The migration's comment says laps without `submission_id` have no fingerprint, while the active code stores `submission_hash` on every new lap; this is harmless but should be corrected as documentation drift.

At this point the reviewed SEC-01 application code is substantially hardened. SEC-01 remains operationally open only because effective enforcement is still `false` during Lua rollout.

#### Sixth implementation follow-up — commit `daab4f2`

This commit resolves the fifth review's final narrow correctness item:

- `LapSubmissionHash` casts race type and checkpoint IDs to integers and lap/split timing fields to floats before hashing, making numeric strings and native numbers canonical-equivalent;
- duplicate checkpoint IDs are rejected with HTTP 422 through the validation `distinct` rule, removing equal-key split-order ambiguity;
- the `submission_hash` migration comment now accurately states that every new lap receives a fingerprint, while only pre-migration rows remain null.

Focused tests cover numeric-string equivalence and duplicate checkpoint rejection. The complete suite passes at 139 tests / 352 assertions, PHPStan reports zero errors, and Pint passes. Effective runtime settings remain a one-second UDP timeout with the strict unverified tier (2/second burst, 30/IP/minute, 15/IP:port/minute).

No new code defect was found in this follow-up. The reviewed SEC-01 application implementation is ready for controlled integration testing with the updated Lua script. SEC-01 should be marked resolved after enforcement is enabled and the real-server positive/negative/token-rotation/retry cases pass.

#### PHP-FPM capacity follow-up — 7 July 2026

Host configuration was inspected directly. The active PHP 8.4 pool for `redesign.hrl.effakt.info` now has:

- `pm = dynamic`;
- `pm.max_children = 30`;
- `pm.min_spare_servers = 8`;
- `pm.max_spare_servers = 15`;
- `pm.max_requests = 1000`;
- `max_execution_time = 120` seconds.

This establishes a finite PHP worker ceiling. No additional web-server concurrency-limit action is tracked by this audit.

#### Lua integration review — 7 July 2026

The deployed candidate `/var/www/redesign_hrl_usr54/data/hrl.lua` was reviewed against `LapSubmissionVerifier` and `StoreLapTimeRequest`. The configured HTTP domain/path was explicitly excluded from this review at the user's direction. The wire contract otherwise aligns: the script publishes `hrl_enabled=1`, protocol `1`, a current and previous token; submits the matching token, map, player name, configured UDP port, and a 8–64-character per-attempt `submission_id`; and rotates the token with a one-token grace window. The backend's exact player-slot, map, protocol, token, and submission validation matches those fields.

All three Lua code findings from this review are now resolved:

1. **Debug lap command — resolved:** the candidate now sets `debug = 0`; the `logtime` branch remains guarded by `debug == 1`, so it is unreachable in this configuration. Keep production copies at zero.
2. **Rotation clock reset — resolved:** rotation storage and comparison now both use `os.time()`, so match-relative `$ticks` resets no longer stall the five-minute rotation.
3. **Stale query markers on unload — resolved:** `OnScriptUnload()` now removes `hrl_enabled`, `hrl_protocol`, `hrl_token`, and `hrl_token_prev` with SAPP's `query_del` command.

Static Lua/PHP contract review now passes. Before enforcement, perform one real end-to-end submission and negative checks for script-unloaded, player-left, map mismatch, current/previous token rotation, and a non-ASCII player name. The last case is important because the HTTP body explicitly converts the Halo name to UTF-8 while `QueryServer` compares raw UDP player bytes without an explicit encoding conversion; static inspection cannot confirm that both paths produce identical bytes for extended characters.

**Non-blocking gameplay observation:** the reported final lap of a map being missed is most plausibly a Lua event-order race. `OnGameEnd()` immediately resets every player's checkpoint state, while `OnPlayerScore()` begins by returning when that state is no longer marked `started`; `TrackCheckpoints()` can also clear the same flag when checkpoint memory returns to zero. A map-ending score has a tighter transition window than an ordinary lap, making it plausible that state is cleared before the score handler submits the lap. This is diagnostic only and was not changed. If the console never prints that lap's JSON, this explanation is strongly supported; if it does print the JSON, investigate transport/backend timing instead. Once HRL enforcement is enabled, a second end-of-map race is possible because the asynchronous backend query may observe the next map or an empty player list and reject the old-map submission.

#### Real-server integration and loopback follow-up — 7 July 2026

A real Lua-originated lap was successfully delivered and persisted, confirming the HTTP client, payload validation, automatic server identity, and lap-persistence path. The audit does not yet treat this as proof of the enforced positive verification path because enforcement state/result was not captured for that request.

That test exposed a rebuild regression: three known UniFi/NAT internal source addresses handled by the legacy controller were no longer rewritten to the game server's public IP. The pending fix introduces `ResolveSubmittingIp` and applies it consistently in both the webhook rate limiter and `LapSubmissionController`, before the IP is used for limiter-tier markers, idempotency, UDP verification, server identity, or storage. The mapping is an exact configured allowlist, not a generic private-address rewrite. Review found no identity disagreement between middleware and controller.

The non-ASCII player-name concern from the Lua review is also resolved in commit `91f14a1`: UDP `player_N` values are normalized from Windows-1252 to UTF-8 before exact comparison, with a regression test.

Verification passes at 141 Pest tests / 355 assertions, including 47 focused webhook tests / 121 assertions; PHPStan reports zero errors, Pint passes, and `git diff --check` passes. One deployment housekeeping item remains: `app/Helpers/ResolveSubmittingIp.php` is currently untracked and must be included when these pending loopback changes are committed/deployed.

**Negative integration result:** with `WEBHOOK_HRL_VERIFY_ENABLED=true` and `WEBHOOK_HRL_VERIFY_ENFORCE=true`, a laptop submission received HTTP 403 and no lap was accepted. The staging log records `reason=missing_hrl_marker`, `enforced=true`, for resolved source `114.23.254.181:2302`. This proves hard rejection is active for a live query response lacking the HRL marker. It also means the enforced positive path still needs a fresh real lap while the UDP query visibly contains `hrl_enabled=1`; otherwise legitimate laps from that server will also be rejected.

**Closure update:** the CLI UDP check subsequently confirmed the updated development server publishes the required HRL fields, and a real Lua-originated lap has already reached and persisted through the rebuilt endpoint. Together with the enforced 403 negative test and automated verification suite, this closes SEC-01 for the staged implementation. The production cutover must deploy the updated Lua script before or alongside enabling enforcement; immediately smoke-test one real lap after cutover. This is a deployment condition, not a remaining audit defect.

### SEC-02 — HTTP remains enabled (High)

`http://redesign.hrl.effakt.info/` returned `200`, not a redirect. On HTTP, Laravel issued session/XSRF cookies without the `Secure` attribute. No `Strict-Transport-Security` header was present on HTTPS.

This permits downgrade and man-in-the-middle exposure. It also allows lap submissions to be altered in transit if clients use the HTTP URL.

**Recommendation:** redirect all HTTP requests to HTTPS, enable HSTS after confirming every subdomain is HTTPS-ready, and explicitly require secure cookies in the deployed environment.

**Follow-up (2026-07-07):** `/api/v1/*` is deliberately excluded from this fix — legacy game-server clients (older Wine/non-browser HTTP stacks) call the lap-submission webhook and can't do TLS, so it must stay reachable over plain HTTP. Scoped the redirect at the Laravel middleware-group level instead of a blanket site-wide rule: `web`-group routes only get `App\Http\Middleware\RedirectIfNotSecure` (HTTP → HTTPS, 301, `production`/`staging` only) and `App\Http\Middleware\AddHstsHeader` (no `includeSubDomains`/`preload`, since REL-01's Reverb websocket isn't TLS-ready and the API on this same host must stay non-TLS); `api`-group routes are untouched. Also set `SESSION_SECURE_COOKIE=true` since web traffic is now always HTTPS. **Depends on the webserver correctly reporting HTTPS to PHP-FPM** (e.g. `fastcgi_param HTTPS on` in the TLS server block) — not verified here, no read access to the FastPanel-managed nginx vhost from this environment.

### SEC-03 — Local/debug runtime on the public deployment (Resolved)

Initial runtime inspection reported `APP_ENV=local`, `APP_DEBUG=true`, and `APP_URL=http://localhost`.

**Follow-up verification, 7 July 2026:** the effective Laravel runtime now reports:

- environment: `staging`;
- debug mode: off;
- application URL: `https://redesign.hrl.effakt.info`.

The immediate disclosure/base-URL risk is resolved. Keep these values deployment-managed and add an automated post-deploy assertion so a future release cannot silently revert to local/debug mode. Production optimisation/config caches were still not present at the original inspection and remain a separate performance/deployment recommendation.

### SEC-04 — Webhook resource-exhaustion inputs (High)

Validation confirms that `splits` is an array but sets no maximum array length. A valid request may therefore insert a large number of child rows in one transaction. `player_time` has no upper bound; split checkpoint IDs, durations, start times, and end times lack realistic ranges and duration positivity checks.

Combined with the unauthenticated endpoint, this creates a practical database/storage/CPU abuse path even below the request-rate limit.

**Recommendation:** cap body size at the proxy and app, cap split count to the protocol maximum, enforce sane numeric ranges, require unique/ordered checkpoint IDs, and apply a daily/server quota.

### SEC-05 — Missing response hardening headers (Medium)

Observed responses lacked:

- Content Security Policy;
- `X-Content-Type-Options: nosniff`;
- clickjacking protection (`frame-ancestors` or `X-Frame-Options`);
- `Referrer-Policy`;
- `Permissions-Policy`;
- HSTS.

The server exposes PHP's exact version through `X-Powered-By`; the front-end server token has appeared as both exact nginx and generic OpenResty across audit probes.

**Recommendation:** define a tested CSP compatible with Livewire/Alpine/Reverb, add the remaining headers at OpenResty or middleware level, and suppress unnecessary version disclosure.

### SEC-06 — Open Reverb origin/resource policy (Medium)

Reverb is configured with `allowed_origins => ['*']`, no connection limit, and application rate limiting disabled. Public channels are reasonable for public leaderboard data, but unrestricted origins allow any site to consume connections and broadcast traffic at the server's expense.

**Recommendation:** allow only intended web origins and enable conservative connection/message limits.

### SEC-07 — Least privilege (Medium)

- `.env` is mode `0644`; every local account that can traverse the known project path can read it. Use `0600` or the minimum required group-readable mode.
- The application DB account has `ALL PRIVILEGES` on the application database. Runtime generally needs data CRUD, not schema alteration or grant-level capabilities.
- Positive check: `.env`, `.git/config`, `composer.json`, and logs were not publicly retrievable through the deployed web root.

### Additional security observations

- Blade uses escaped output; no raw `{!! !!}` output was found.
- User-derived values in the reviewed active code use Eloquent/query bindings; no interpolated active SQL was found.
- The `.env` file is ignored and not tracked by Git.
- CSRF protection covers web/Livewire routes, but the API webhook is intentionally outside CSRF and currently lacks the proposed live UDP verification gate.
- Composer and npm audits found no known advisories. This is point-in-time evidence, not a substitute for continuous scanning.

## Performance and scalability

### Deployed route timing

Three sequential HTTPS requests per route were sampled from the deployment. Values below are TTFB ranges; network latency from the audit environment is included.

| Route | TTFB | Compressed response |
|---|---:|---:|
| `/` | **1.80–1.97s** | ~9.7 KB |
| `/players` | 0.36–0.42s | ~25.2 KB |
| `/players/5` | 0.26–0.27s | ~12.2 KB |
| `/servers/5` | 0.17–0.18s | ~22.7 KB |
| `/maps/1` | 0.067–0.072s | ~16.7 KB |
| `/servers` | 0.054–0.055s | ~8.6 KB |
| `/maps` | 0.030–0.047s | ~7.8 KB |
| `/api/v1/servers` | 0.030–0.033s | 484 B |
| `/api/v1/maps/1/leaderboard` | 0.041–0.060s | ~27.1 KB |

Current data volume is only 6 servers, 10 maps, 817 players, 1,657 laps, and 429 splits.

### PERF-01 — Home page recomputation (High)

The home page repeatedly calls `GlobalRanking::mapRank()`, `GlobalRanking::forPlayer()`, `RecordHistory::events()`, and `MostActiveServer::scores()` while evaluating recent laps and candidate highlights. Several calls load and rank broad lap collections again inside loops. The consistent ~1.8s TTFB demonstrates this is already user-visible at a tiny scale.

**Recommendation:** profile query count/time, compute shared ranking/history snapshots once per request, eliminate per-lap full recalculations, and cache/materialise the expensive home aggregates. Invalidate or refresh them after accepted lap submissions.

### PERF-02 — Missing indexes (Medium)

The production `lap_times` table has only the primary key and single-column foreign-key indexes. There are no indexes on:

- `players.hash`;
- `maps.name`;
- `(servers.ip, servers.port)`;
- common lap access patterns such as `(map_id, time, player_id)`, `(server_id, map_id, time, player_id)`, `(server_id, created_at)`, or `(player_id, created_at)`.

`firstOrCreate()` lookups and ranking/activity queries will increasingly scan/sort as history grows. Identity columns also lack unique constraints, so concurrent submissions can create duplicate logical records.

**Recommendation:** use actual slow-query/`EXPLAIN` evidence to select composite indexes, then add unique identity constraints after cleaning existing duplicates.

### PERF-03 — Full result computation before pagination (Medium)

Global rankings load all qualifying laps and rank/deduplicate them in PHP. UI pagination occurs after the complete ranking is built. The map leaderboard API returns every ranked player with no pagination. This is acceptable at 1,657 laps but has linear memory/transfer growth.

`/players` already needs ~0.4s, and `/api/v1/maps/1/leaderboard` returns 27 KB at current scale.

**Recommendation:** define scale targets, add API pagination, and move stable best-lap/ranking projections to efficient SQL or maintained snapshots when thresholds are reached.

### PERF-04 — Deployment caching (Medium)

- Content-hashed JS/CSS assets returned ETags but no explicit `Cache-Control: public, max-age=..., immutable`.
- HTML correctly uses private/no-store semantics, but every anonymous page creates session/XSRF cookies, reducing opportunities for shared page caching.
- No Laravel config or route cache was present.
- The frontend build is ~1.1 MB on disk. It includes many font formats/subsets and weights; the main JS is 88.6 KB and CSS is 59.8 KB before compression.

**Recommendation:** add immutable caching for hashed assets, production Laravel caches, and review whether anonymous read-only GETs need sessions. Measure font requests in a browser waterfall before pruning formats/weights.

## Reliability and operations

### REL-01 — Public real-time client misconfiguration (High)

The deployed JS bundle contains:

```text
wsHost: "localhost"
wsPort/wssPort: "8081"
forceTLS: false
```

For a public visitor, `localhost` means the visitor's own machine. On an HTTPS page, a non-TLS WebSocket is also mixed content. Real-time leaderboard/home/server updates therefore cannot reliably connect.

**Recommendation:** build with the public WebSocket hostname and `wss`, proxy it through the public TLS endpoint, and add an automated browser/WebSocket smoke test against the deployed environment.

**Follow-up (2026-07-07):** two of three causes fixed. `VITE_REVERB_HOST`/`PORT`/`SCHEME` were aliases of the server-side `REVERB_*` vars (correctly `localhost` for Laravel-to-Reverb loopback publishing, wrong for the public bundle) — decoupled into their own literal values (`redesign.hrl.effakt.info`, `443`, `https`) and rebuilt; confirmed the built bundle now references the real hostname. The Reverb server process also wasn't running at all for this app — started (not yet made durable via systemd/supervisor, see OPS-01). **Still blocking end-to-end**: no nginx reverse-proxy exists for the WebSocket upgrade (`curl -I https://redesign.hrl.effakt.info/app/<key>` → `404`); needs a `location` block proxying to `127.0.0.1:8081` with `Upgrade`/`Connection` headers forwarded — see docs/deployment.md for the exact config. Not applied here: no access to the FastPanel-managed nginx vhost from this environment.

### OPS-01 — Background service deployment is incomplete/unverified (Medium)

Both broadcast events implement `ShouldBroadcast`, so they require a queue worker. Live status also requires the scheduler, and Reverb requires a persistent server process. The repository contains no deployment/service definitions, and the readable Supervisor configuration contains workers/Reverb only for other HRL installations—not this redesign path.

The audit sandbox cannot see host PID state or the user's cron, so it cannot conclusively prove the processes are stopped. Deployment should nevertheless treat all three as unverified until checked from the host.

**Recommendation:** create deployment-managed queue, Reverb, and scheduler services with automatic restart, health checks, logs, and alerts. Add `queue:restart` and asset/config cache steps to the release procedure.

### Logs and health checks

- Access logs contained 47 HTTP 500 responses among roughly 29,700 recorded responses (~0.16%), concentrated on core routes during active development. Recent visible causes included a missing view, Reverb port conflict, and temporary database connection failures.
- `/up` returned 200 but is Laravel's shallow built-in health route; it does not demonstrate DB, queue, scheduler, or Reverb health.
- Operational docs acknowledge that queued broadcasting silently fails without a worker.

**Recommendation:** expose separate dependency-aware readiness checks and alert on 5xx rate, queue age/failures, scheduler freshness, and WebSocket connectivity.

## Tests and code quality

### Passing checks

| Check | Result |
|---|---|
| Pest | **139 passed, 352 assertions** |
| PHPStan/Larastan level 5 | **0 errors** |
| Pint (`--test`) | Passed |
| PHP syntax | Passed for app, routes, config, migrations, factories, and tests |
| Composer audit | No advisories |
| npm production dependency audit | No advisories |

The tests give meaningful coverage to rankings, record history, server activity, lap submission behaviour, API responses, live-update listener logic, routes, splits, and live-server refresh logic.

### TEST-01 — Important coverage/enforcement gaps (Medium)

- No test verifies either API rate limiter returns 429 at the correct boundary.
- Webhook validation does not test huge split arrays, numeric extremes, duplicate checkpoints, negative split durations, or malicious display strings.
- No real UDP client integration test exists; it is always faked.
- No browser/E2E coverage exists for Alpine modals, keyboard use, responsive navigation, real Echo/Reverb transport, or JavaScript console errors.
- `PlayerShow` has only route smoke coverage; its global-only favourite-server/display logic lacks focused tests.
- No coverage percentage or mutation testing is produced, so 104 passing tests should not be interpreted as comprehensive coverage.
- Semgrep is configured but not installed and has never been validated/run.
- No CI workflow is present, so passing checks are not enforced on push or deployment.
- PHPStan is at level 5 rather than a stricter production baseline.

### QUAL-01 — Tooling/documentation baseline (Low)

- Rector dry-run completed without runtime errors but proposed changes in **46 files**, so the configured all-in-one `composer check` cannot currently be a clean gate.
- Documentation contains stale state: test counts say 257 assertions rather than 264; performance docs describe portions of the app as mock/not implemented; current data counts differ from documented counts.
- Stock example tests and inactive `*.php-legacy` files add noise and can confuse audits/searches, although they are not active routes.

## Accessibility and UX

### A11Y-01 — Keyboard and modal semantics (Medium)

- Podium cards use clickable `<div>` elements with no keyboard role, focusability, or key handler.
- Modal containers do not declare `role="dialog"`, `aria-modal="true"`, or an accessible title association.
- No focus trap, initial-focus placement, focus restoration, or modal Escape handler was found.
- No skip link was found.
- No explicit `:focus-visible` styling or reduced-motion handling was found despite substantial transitions/flicker effects.
- Many table rows are buttons, which is positive for keyboard reachability, and icon-only close/menu buttons have labels.

**Recommendation:** test against WCAG 2.2 AA using keyboard-only navigation and a screen reader, then add semantic controls/dialog behaviour, visible focus, skip navigation, and `prefers-reduced-motion` support.

## SEO and discoverability

### SEO-01 — Minimal metadata (Low)

The layout provides a title and viewport only. It lacks descriptions, canonical URLs, Open Graph/Twitter metadata, structured data, and a sitemap. Detail-page titles are generic (for example, `Player | Halo Race Leaderboard`) rather than including the player/map/server name. `robots.txt` allows crawling.

**Recommendation:** add dynamic descriptive titles/meta, canonical URLs, social cards, and a sitemap for public leaderboard entities. Decide whether this redesign environment should be indexed before launch.

## Recommended order of work

1. Add the source-server UDP verification gate (HRL marker/version/token, matching map, and online player) before auto-creation or lap storage; also cap and validate payload size.
2. Enforce HTTPS/HSTS and secure cookies; retain the corrected staging environment, debug-off setting, and canonical HTTPS URL.
3. rebuild and verify Echo/Reverb with a public `wss` endpoint; manage queue/Reverb/scheduler as services.
4. remove repeated home-page calculations and set a sub-500ms server-response target.
5. add security headers, Reverb origin/rate limits, tighter file permissions, and reduced DB privileges.
6. add evidence-based composite indexes, API pagination, and immutable static-asset caching.
7. add CI plus rate-limit, abuse-case, browser, accessibility, and real-time transport tests.
8. refresh documentation and make the configured quality gate clean.

## Audit limitations

- This was not a destructive penetration test; no forged lap was submitted and rate limits were not exhausted.
- No authenticated functionality exists to assess.
- No Lighthouse/browser automation, screen reader, load test, or external port scan was run.
- Host-level OpenResty/nginx-compatible, Supervisor, and cron configuration was only partially readable; process state was isolated by the sandbox.
- Dependency advisory results reflect registries at the audit time only.
- Performance figures are a small observational sample, not a controlled load test.
