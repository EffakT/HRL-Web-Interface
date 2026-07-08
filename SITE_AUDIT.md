# Site Audit — Halo Race Leaderboard Redesign

**Audit date:** 7 July 2026; resolved/mitigated findings revalidated 8 July 2026 (Pacific/Auckland)

**Scope:** repository, deployed site, Laravel runtime configuration, dependencies, automated tests, static analysis, database indexes/privileges, response headers, representative route timing, accessibility/SEO markup, and recent logs.
**Constraint:** audit only. No application code or configuration was changed.

## Executive summary

**Overall status: not production-ready.**

The application logic has a strong automated baseline. The latest in-progress-worktree run passed **181 Pest tests / 506 assertions** when webhook enforcement was explicitly disabled for the test process. Without that override, the suite still inherits staging enforcement and older webhook fixtures fail. PHPStan (debug/single-process mode), Pint, the production Vite build, and `git diff --check` pass on the current in-progress worktree.

The largest risks are outside those passing checks:

1. Remaining production risks include unverified Any Order/Rally split compatibility, report-only rather than enforced CSP, the accepted FastPanel database-privilege limitation, durable asset caching, CI enforcement, and browser tests.

## Prioritised findings

| ID | Severity | Finding |
|---|---:|---|
| SEC-01 | Mitigated | Live UDP server verification is enforced and rejects off-server submissions; updated Lua remains a production-cutover requirement |
| SEC-02 | Mitigated | Web routes redirect safely to HTTPS; plaintext API remains available; HSTS is deliberately deferred |
| SEC-03 | Resolved | Runtime changed to `staging`, debug off, with the correct HTTPS URL |
| REL-01 | Resolved | Client rebuilt with the real public wss hostname, Reverb running, WebSocket proxy added in Nginx Proxy Manager — verified with a real `101 Switching Protocols` |
| PERF-01 | Resolved | Final Home payload is cached; generation keys and a distributed lock address invalidation races and stampedes; live cache-hit TTFB remains ~40–60ms |
| SEC-04 | Mitigated; compatibility check open | Positive durations, bounded splits, contiguous-ID sets, authoritative backfill, atomic baselines, and unique map names are deployed; real Any Order/Rally payloads still need validation against Lua's ordered-ID normalizer |
| SEC-05 | Mitigated | Core response headers are live and normal web/API responses suppress PHP; the Livewire asset route still discloses PHP/8.4.12, while CSP remains report-only |
| SEC-06 | Resolved live; commit pending | Reverb now restricts browser origins, caps global connections at 500, limits messages to 60/minute per connection, and terminates offenders; live Pusher-layer checks pass |
| SEC-07 | Partially mitigated; DB risk accepted | `.env` is now owner-only; the runtime DB account retains database-wide privileges because FastPanel makes separate migration/runtime users impractical |
| DATA-01 | Resolved live; commit pending | User confirmed `(hash, name)` is the intended identity; lookup and deployed composite uniqueness now match it without merging legitimate shared-hash players |
| PERF-02 | Resolved live; commit pending | Evidence-backed indexes and all server/map/player identity constraints are deployed and selected by MySQL; unused speculative indexes were correctly omitted |
| PERF-03 | Resolved live; commit pending | Map API responses are bounded and paginated, extreme pages are safely clamped, and measured thresholds define when full-set ranking must be redesigned |
| PERF-04 | Resolved | Fingerprinted assets receive one-year public immutable caching without caching HTML/API; Laravel config, routes, events, and views are cached |
| TEST-01 | Medium | Security, rate-limit, browser, WebSocket, and production-path test gaps |
| OPS-01 | Resolved | Reverb, eight queue workers, and the scheduler are Supervisor-managed with active logs and automatic restart |
| A11Y-01 | Medium | Interactive podium cards and modals have keyboard/focus deficiencies |
| SEO-01 | Low | Minimal metadata and no sitemap/canonical/social metadata |
| QUAL-01 | Low | Rector dry-run and documentation are not at a clean current baseline |

## Resolved/mitigated finding revalidation — 8 July 2026

| Finding | Verdict | Revalidation evidence |
|---|---|---|
| SEC-01 | **Mitigated, verified** | Effective runtime has HRL verification and enforcement enabled with a one-second UDP timeout. A fresh invalid HTTP lap returned `403 missing_hrl_marker`; its unique submission ID was absent from `lap_times`. The durable submission unique index and `submission_hash` column remain deployed. The development server's HRL query fields and one real Lua delivery were confirmed previously. This remains a mitigation rather than cryptographic authentication, and updated Lua must be deployed at production cutover. |
| SEC-02 | **Mitigated, verified** | Fresh live HTTP web routes return `301` to the fixed HRL HTTPS hostname while preserving paths/query strings; HTTP `/api/v1/servers` remains `200`. Forged forwarded host/port no longer changes the target, forged source IP/proto remains blocked by the edge, secure cookies are enabled, and HTTPS no longer emits HSTS. The API's intentional plaintext transport remains an accepted compatibility limitation. |
| SEC-03 | **Resolved, verified** | Effective runtime reports `staging`, debug off, HTTPS application URL, secure cookies, and a public 404 contains no stack trace/debug output. |
| REL-01 | **Resolved, verified for the original defect** | The exact JS asset served by the homepage contains the public hostname and no loopback endpoint; public WSS and Origin enforcement pass. Supervisor durability is now separately resolved under OPS-01. |
| PERF-01 | **Resolved, verified** | Commit `bb3355b` adds final-payload caching, generation-based invalidation, and a distributed rebuild lock. Warm tests and stale-generation coverage pass; a fresh live request returned 62ms TTFB. |
| SEC-04 | **Mitigated; compatibility check open** | Positive durations and sorted contiguous-ID validation are implemented; baseline assignment uses compare-and-set; map names are unique; all nine historical layouts are backfilled and all 72 split-bearing laps validate as `1..N`. A real Any Order/Rally submission remains necessary because Lua's raw-ID normalization assumes the ordered cumulative `1,3,7,15...` pattern. |
| SEC-05 | **Mitigated, verified with one disclosure exception** | Core hardening headers are present on fresh web and plaintext API responses and those responses omit `X-Powered-By`. The Livewire JavaScript route still emits `X-Powered-By: PHP/8.4.12`. CSP is report-only and has no reporting endpoint, so enforcement remains open. |
| SEC-06 | **Resolved in the active deployment; commit pending** | Effective config is exact-origin/500 connections/60 messages per minute with termination. Independent live checks received `connection_established` for the real Origin and Pusher error `4009` for `evil.example`; the new config test and full suite pass. |
| SEC-07 | **Partially mitigated; residual risk accepted** | `.env` is now `0600`, owned by the application account, and the staging app boots normally. MySQL still grants the runtime account `ALL PRIVILEGES` on only its application database; privilege reduction is explicitly deferred because FastPanel makes separate admin/migration and runtime users difficult to maintain. |
| DATA-01 | **Resolved live; commit pending** | User confirmed `(hash,name)` is the real identity. The deployed composite unique index has zero historical collisions; `firstOrCreate()` and exact-set retry classification use the same pair; shared hashes and shared names independently remain allowed. |
| PERF-02 | **Resolved live; commit pending** | Deployed indexes on `lap_times(server_id,map_id,player_id,time)`, `lap_times(server_id,created_at)`, and unique `players(hash,name)` are selected by real MySQL plans. The composite index also serves hash-prefix lookup. |
| PERF-03 | **Resolved live; commit pending** | Live API pagination returns standard `data`/`links`/`meta`, defaults to 50 and caps at 100. Extreme pages are clamped before offset multiplication and return 200; independent timings reproduce ~197ms global and ~23ms largest-map calculations, with concrete revisit thresholds documented. |
| PERF-04 | **Resolved, verified live/runtime** | Fingerprinted JS/CSS/font responses advertise `Cache-Control: public, max-age=31536000, immutable`; HTML remains private/no-store and the plaintext API remains no-cache. `php artisan about --only=cache` reports config, events, routes, and views all cached. |
| OPS-01 | **Resolved, verified** | Supervisor definitions exist for Reverb, eight queue workers, and `schedule:work`; fresh logs show minute-by-minute server refreshes and broadcast jobs completing. Public WSS remains reachable. |
| Lua debug/rotation/unload findings | **Resolved by static verification** | Candidate Lua has `debug = 0`, stores and compares rotation time with `os.time()`, and deletes all four HRL query fields in `OnScriptUnload()`. Real unload/rotation boundary behavior should still be included in the production smoke test. |
| NAT/loopback regression | **Resolved in code** | `ResolveSubmittingIp.php` is now tracked in commit `9d1bb4a`; both rate-limiter middleware and the controller use it before identity-sensitive work. |

### Reproducible checks

SEC-02 revalidation commands now produce `301`, `200`, no HSTS output, and a redirect to the real HRL hostname despite spoofed forwarded host/port input:

```bash
curl -sSI http://redesign.hrl.effakt.info/ | sed -n '1p;/^location:/Ip'
curl -sSI http://redesign.hrl.effakt.info/api/v1/servers | sed -n '1p'
curl -sSI https://redesign.hrl.effakt.info/ | grep -i '^strict-transport-security:'
curl -sSI http://redesign.hrl.effakt.info/maps \
  -H 'X-Forwarded-Host: evil.example' \
  -H 'X-Forwarded-Port: 444' | sed -n '1p;/^location:/Ip'
```

Under the current compatibility decision, expected results are: web HTTP redirects to the equivalent HTTPS URL on `redesign.hrl.effakt.info`; API HTTP remains reachable; HTTPS web responses do not advertise HSTS; forwarded host/port input cannot change the redirect target. Because HSTS is host-wide, path-scoping the header does not preserve HTTP access for HSTS-aware clients. A future clean solution is a separate API hostname or TLS-capable game client, after which HSTS can safely be reconsidered.

SEC-01 negative test (use a fresh `submission_id`; expected HTTP 403 and no inserted row):

```bash
curl -i http://redesign.hrl.effakt.info/api/v1/laps \
  -H 'Content-Type: application/json' \
  --data '{"port":2302,"player_hash":"audit-negative","player_name":"AuditNotOnline","map_name":"bloodgulch","race_type":0,"player_time":99,"hrl_token":"invalid-token","submission_id":"audit-negative-unique-001"}'
```

SEC-03 runtime check:

```bash
php artisan tinker --execute='dump([app()->environment(), config("app.debug"), config("app.url"), config("session.secure")]);'
```

REL-01 proxy handshake check (expected `101 Switching Protocols`):

```bash
curl --http1.1 --max-time 5 -i \
  -H 'Connection: Upgrade' \
  -H 'Upgrade: websocket' \
  -H 'Sec-WebSocket-Version: 13' \
  -H 'Sec-WebSocket-Key: SGVsbG9Xb3JsZDEyMzQ1Ng==' \
  https://redesign.hrl.effakt.info/app/test
```

The focused SEC-01 suite currently requires an explicit test-process override because staging enforcement is on:

```bash
WEBHOOK_HRL_VERIFY_ENFORCE=false php artisan test --compact \
  tests/Feature/LapSubmissionTest.php \
  tests/Feature/LapSubmissionVerifierTest.php \
  tests/Feature/LapSubmissionHashTest.php
```

Without that override, the current command fails 21 tests through `422 submission_id required` or enforced `403` responses. Test configuration should eventually set its own deterministic enforcement value instead of inheriting staging `.env` state.

## Security

### SEC-01 — Unauthenticated public write endpoint (Mitigated)

`POST /api/v1/laps` intentionally has no conventional login/API-key authentication. At the initial audit, its only control was a per-IP rate limit; the current UDP live-server verification, enforcement, tiered limiter, and idempotency controls are documented in the follow-ups below.

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

At that follow-up, verification passed at 141 Pest tests / 355 assertions, including 47 focused webhook tests / 121 assertions; PHPStan reported zero errors, and Pint and `git diff --check` passed. `app/Helpers/ResolveSubmittingIp.php` has since been committed in `9d1bb4a`, resolving the deployment housekeeping item. On 8 July, the focused tests still pass with `WEBHOOK_HRL_VERIFY_ENFORCE=false`; their default invocation now inherits staging enforcement and fails, as documented in the revalidation section above.

**Negative integration result:** with `WEBHOOK_HRL_VERIFY_ENABLED=true` and `WEBHOOK_HRL_VERIFY_ENFORCE=true`, a laptop submission received HTTP 403 and no lap was accepted. The staging log records `reason=missing_hrl_marker`, `enforced=true`, for resolved source `114.23.254.181:2302`. This proves hard rejection is active for a live query response lacking the HRL marker. It also means the enforced positive path still needs a fresh real lap while the UDP query visibly contains `hrl_enabled=1`; otherwise legitimate laps from that server will also be rejected.

**Closure update:** the CLI UDP check subsequently confirmed the updated development server publishes the required HRL fields, and a real Lua-originated lap has already reached and persisted through the rebuilt endpoint. Together with the enforced 403 negative test and automated verification suite, this establishes the SEC-01 mitigation for the staged implementation. It is labelled mitigated—not fully authenticated—because the documented same-server-operator and plaintext on-path trust limitations remain. The production cutover must deploy the updated Lua script before or alongside enabling enforcement; immediately smoke-test one real lap after cutover.

**Revalidation (2026-07-08): mitigation active.** Effective configuration has verification and enforcement enabled. A fresh deliberately invalid HTTP submission returned `403 {"success":false,"reason":"missing_hrl_marker"}` with the strict two-request burst limit, and a direct database check confirmed its unique `submission_id` was not stored. The durable `(server_id, submission_id)` unique index and `submission_hash` column remain present.

### SEC-02 — HTTP remains enabled (High)

`http://redesign.hrl.effakt.info/` returned `200`, not a redirect. On HTTP, Laravel issued session/XSRF cookies without the `Secure` attribute. No `Strict-Transport-Security` header was present on HTTPS.

This permits downgrade and man-in-the-middle exposure. It also allows lap submissions to be altered in transit if clients use the HTTP URL.

**Recommendation:** redirect web routes from HTTP to HTTPS and explicitly require secure cookies. Defer HSTS under the current compatibility decision: HSTS applies to the hostname rather than selected URL paths, so an HSTS-aware client that has visited the HTTPS website will upgrade later HTTP API requests on the same hostname. Reconsider HSTS after moving the legacy API to a separate hostname or making game clients TLS-capable.

**Follow-up (2026-07-07):** `/api/v1/*` is deliberately excluded from this fix — legacy game-server clients (older Wine/non-browser HTTP stacks) call the lap-submission webhook and can't do TLS, so it must stay reachable over plain HTTP. Scoped the redirect at the Laravel middleware-group level instead of a blanket site-wide rule: `web`-group routes only get `App\Http\Middleware\RedirectIfNotSecure` (HTTP → HTTPS, 301, `production`/`staging` only) and `App\Http\Middleware\AddHstsHeader` (no `includeSubDomains`/`preload`, since REL-01's Reverb websocket isn't TLS-ready and the API on this same host must stay non-TLS); `api`-group routes are untouched. Also set `SESSION_SECURE_COOKIE=true` since web traffic is now always HTTPS. **Depends on the webserver correctly reporting HTTPS to PHP-FPM** (e.g. `fastcgi_param HTTPS on` in the TLS server block) — not verified here, no read access to the FastPanel-managed nginx vhost from this environment.

**Revalidation (2026-07-08, latest): mitigated.** Laravel now trusts only forwarded source IP and protocol—not forwarded host/port. Live HTTP web routes return `301` to `https://redesign.hrl.effakt.info`, preserve paths/query strings, and ignore a public caller's forged `X-Forwarded-Host`/`X-Forwarded-Port`; forged protocol and source-IP attempts are also overwritten by the edge. HTTP API access remains available, and cookies are Secure. `AddHstsHeader` has been removed and fresh HTTPS responses contain no HSTS header, matching the compatibility decision. The residual plaintext API transport risk is explicitly accepted until clients gain TLS support or the API moves to a separate hostname.

### SEC-03 — Local/debug runtime on the public deployment (Resolved)

Initial runtime inspection reported `APP_ENV=local`, `APP_DEBUG=true`, and `APP_URL=http://localhost`.

**Follow-up verification, 7 July 2026:** the effective Laravel runtime now reports:

- environment: `staging`;
- debug mode: off;
- application URL: `https://redesign.hrl.effakt.info`.

The immediate disclosure/base-URL risk is resolved. Keep these values deployment-managed and add an automated post-deploy assertion so a future release cannot silently revert to local/debug mode. Production optimisation/config caches were still not present at the original inspection and remain a separate performance/deployment recommendation.

**Revalidation (2026-07-08): resolved.** Effective values remain `staging`, `APP_DEBUG=false`, `APP_URL=https://redesign.hrl.effakt.info`, and secure session cookies enabled. A fresh nonexistent public route returned a generic HTTP 404 with no exception or stack trace.

### SEC-04 — Webhook resource-exhaustion inputs (mitigated; compatibility check open)

**Revalidation (2026-07-08): the identified security/integrity defects are mitigated.** Validation now caps a payload at 20 splits, bounds lap time to `(0, 3600]`, requires each split duration to be positive and no longer than the lap, bounds legacy timestamps, rejects duplicate IDs, and validates the sorted checkpoint-ID set as exactly `1..N`. Sorting before comparison correctly permits a payload such as `[3,1,2]`; JSON order is not treated as race order.

Baseline and identity hardening are also deployed:

- first-baseline assignment is a conditional `UPDATE ... WHERE checkpoint_count IS NULL`, so only one concurrent writer wins;
- `maps.name` has a real unique index and the job retries a map-creation race;
- the prior duplicate `bloodgulch` row was safely merged;
- all nine deployed maps were backfilled from historical splits (counts 4–14), with no duplicate names remaining;
- all 72 deployed split-bearing laps independently checked as complete contiguous `1..N` sets;
- checkpoint-count variants are capped, and race types now receive separate map identities.

The current worktree passes **181 tests / 506 assertions**, including zero/negative durations, gapped/arbitrary IDs, out-of-order valid IDs, database uniqueness, compare-and-set behavior, variant limits, separate Any Order/Rally map identities, Reverb policy, deployed-index structure, and API pagination. PHPStan (single-process/debug mode), Pint, the production build, and `git diff --check` also pass.

**Do not mark the gameplay compatibility aspect fully closed until one real Any Order and one Rally lap are submitted.** The backend accepts an out-of-order *permutation* of `1..N`, which is correct. However, `hrl.lua::NormalizeCheckpointId()` converts Halo's raw ordered cumulative pattern `(0,1,3,7,15,...)` to sequential IDs using integer `log2(raw+1)`. That assumption is valid for ordered progression, but an Any Order completion bitmask may not follow the cumulative pattern and can therefore normalize two different states to the same ID or skip an ID. The backend would then correctly reject the resulting duplicate/gapped split set with 422, potentially dropping an otherwise valid lap. Existing automated tests manufacture `[3,1,2]`; they do not exercise the Lua/game-memory producer.

Closure test: complete real laps in race types 1 and 2 and inspect the printed JSON plus HTTP result. The split IDs may be in any order, but after sorting they must equal `1..N`, and the API must return 200. If Lua emits duplicates/gaps, fix checkpoint extraction for that mode or temporarily apply the strict sequence rule only to race type 0; do not weaken positivity, count ceilings, or distinctness globally.

Low-severity namespace hardening remains advisable: suffix-derived names can collide with a legitimate raw map already ending in `-anyorder`, `-rally`, or `-splits-N`; a 255-character raw name becomes too long after suffixing; the variant-count `LIKE` pattern does not escape `%`/`_`; and concurrent creation of different-count variants can exceed the configured cap even though the absolute 20-checkpoint ceiling still bounds the outcome. Prefer explicit `base_name`, `race_type`, and layout columns with a composite unique key long-term, or at minimum constrain/escape raw names and make the cap check transactional.

### SEC-05 — Response hardening headers (mitigated; Medium residual)

**Revalidation (2026-07-08): mitigated, not fully resolved.** Fresh HTTPS web and plaintext HTTP API responses now include `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, a restrictive `Permissions-Policy`, and a same-origin CSP covering the WSS endpoint. `X-Powered-By` is absent. Four focused feature tests pass, and Livewire uses its CSP-safe bundle. HSTS remains deliberately absent under SEC-02's same-host plaintext API compatibility decision and is not counted against this finding.

The CSP is currently `Content-Security-Policy-Report-Only`, so it does not block violations. It also has no `report-uri`/`report-to` destination; violations are visible only in an observer's browser console, which does not provide real-traffic telemetry. Keep SEC-05 mitigated until representative interactive/browser coverage is clean and the policy is enforced. If a report-only observation period is required, add a CSP report collector first; otherwise the period cannot reveal violations from ordinary users.

**Version-disclosure correction (2026-07-08):** the suppression is not universal. Normal HTML and API responses omit `X-Powered-By`, but a fresh request to the versioned Livewire JavaScript URL returned `X-Powered-By: PHP/8.4.12`. That route bypasses or precedes the application's normal security-header middleware. Suppress `expose_php` at PHP-FPM/FastPanel level or strip this header at NPM for all responses; middleware-only removal cannot close the route-level exception.

### SEC-06 — Open Reverb origin/resource policy (Medium)

Reverb is configured with `allowed_origins => ['*']`, no connection limit, and application rate limiting disabled. Public channels are reasonable for public leaderboard data, but unrestricted origins allow any site to consume connections and broadcast traffic at the server's expense.

**Revalidation (2026-07-08): resolved in the active deployment.** Effective configuration now reports:

- `allowed_origins: ['redesign.hrl.effakt.info']`;
- `max_connections: 500` globally;
- message rate limiting enabled at 60 attempts per 60 seconds, per connection;
- `terminate_on_limit: true`;
- 10,000-byte message/request caps retained;
- client events accepted from `members` only.

The supervisor-managed Reverb process was restarted. Independent live Pusher-protocol checks—not merely an HTTP upgrade—confirmed that the real `https://redesign.hrl.effakt.info` Origin receives `pusher:connection_established`, while `https://evil.example` receives Pusher error `4009` (`Origin not allowed`) after the WebSocket upgrade. The documented live rate test sent 61 messages on one connection: 60 were accepted and the 61st was rejected/terminated. The 500-connection ceiling was confirmed loaded but was deliberately not exhausted in a live load test.

The new focused configuration test passes, and the complete current suite passes at **181 tests / 506 assertions** alongside PHPStan (single-process/debug mode), Pint, the production build, and `git diff --check`. Installed Reverb v1.10.2 matches allowed origins against the parsed hostname, so the scheme-free configured value is correct.

Residual limitations are accepted defence-in-depth items rather than blockers for this finding: Origin is a browser policy, not authentication; the message limiter keys by connection ID; and the 500 ceiling is global rather than per-IP. An edge per-IP WebSocket connection limit in Nginx Proxy Manager would further reduce one-source exhaustion, but the user has kept that infrastructure change out of scope. Monitor real connection counts before tuning the provisional 500 ceiling.

**Release hygiene:** `config/reverb.php` is modified and `tests/Feature/ReverbConfigTest.php` is still untracked. Commit both before a tracked-files-only deployment; otherwise the active fix can be lost. Add the optional `REVERB_APP_ALLOWED_ORIGIN`, `REVERB_APP_MAX_CONNECTIONS`, and rate-limit overrides to `.env.example` if operators are expected to tune them rather than use the secure defaults.

### SEC-07 — Least privilege (partially mitigated; database risk accepted)

**Revalidation (2026-07-08): the secret-file issue is resolved.** `.env` is now mode `0600` and owned by the application account, rather than its previous locally world-readable `0644`. Laravel still boots in staging with debug disabled, and the full suite remains green after the permission change. `.env`, `.git/config`, `composer.json`, and logs also remain unavailable through the deployed web root.

The effective MySQL grants remain:

- global scope: `USAGE` only;
- application database: `ALL PRIVILEGES` on `redesign_hrl.*`;
- no `GRANT OPTION`.

The application therefore cannot reduce its own grant. A strict design would use a CRUD-only runtime account and retain the current broader account solely for migrations, but the user has explicitly decided not to pursue that because FastPanel makes separate runtime/migration credentials and privilege management difficult. Record this as an **accepted residual risk**, not an outstanding remediation item. The exposure is limited to this application's database rather than all databases, but a successful application compromise could still alter/drop its schema or data. Revisit only if FastPanel's account management improves or the deployment moves to independently managed database credentials.

### DATA-01 — Shared player-hash identity (Resolved)

The initial audit correctly rejected a plain `UNIQUE(players.hash)` constraint: 820 player rows contain only 246 distinct hashes, with up to 137 distinct names sharing one value and every repeated-hash group carrying real history. The user then supplied the missing protocol fact: the client no longer guarantees one hash per player, and **`(hash, name)` together is the intended identity key**.

Revalidation confirms the fix is consistent end-to-end:

- the deployed database had zero duplicate `(hash,name)` pairs before migration;
- batch 22 deploys `UNIQUE(hash,name)` without merging any history;
- `Player::firstOrCreate()` now matches on the same two fields;
- identical hashes with different names remain allowed, as do identical names with different hashes;
- a concurrent insert collision is explicitly classified and retried;
- the map-name classifier was tightened to avoid confusing SQLite's `(hash,name)` violation with `maps.name` merely because both contain a `name` column;
- focused tests cover constraint semantics and classifier separation.

The unique index is ordered `(hash,name)`, so it replaces the redundant plain hash index while retaining a `ref` plan for hash-prefix lookup; a full identity lookup is a one-row `const` plan. DATA-01 is resolved under the user-confirmed identity model. Future changes to whether renames should preserve identity would be a product-model decision, not a defect in this implementation.

**Classifier hardening closed (2026-07-08):** `violatesPlayerIdentityUniqueness()` now sorts a local copy of the exception column list and requires the exact `['hash','name']` set, while retaining the real MySQL index-name fallback. A constructed unrelated hash-only violation is explicitly tested and returns false, so a future constraint merely containing `hash` cannot be silently retried as a player-creation race.

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

#### Revalidation after the 8 July optimisation

**Verdict: partially improved, not resolved; retain High severity.** The current uncommitted `Home` change correctly shares the seven-day recent-lap collection, computes the current global ranking once, and memoizes identical player/lap exclusion lookups. This is a real improvement over the measured 132-query/~2.3s calculation, but the remaining work is still far above a reasonable homepage budget. `docs/performance.md` currently labels the issue “Fixed”; that wording overstates the measured result and should say “partially improved” until the closure target below is met.

Fresh measurements against the live site:

- eight homepage requests: **1.395–1.690s TTFB**, mean **1.503s**, median approximately **1.481s**;
- three `/api/v1/servers` controls: 0.067–0.101s TTFB;
- three `/up` controls: 0.036–0.043s TTFB;
- one directly instrumented `Home::mount()` call: **1,537.6ms**, **94 SQL queries**, with only **169.7ms** reported inside the database;
- dataset during measurement: 1,668 laps, 13 active-server laps in the seven-day window, 820 players, and five active servers.

The gap between ~170ms of SQL execution and ~1.54s elapsed shows the dominant remaining cost is repeated Eloquent hydration, relationship loading, collection grouping/sorting, and ranking in PHP—not raw database latency alone. Adding indexes may help individual queries, but cannot close PERF-01 by itself.

Remaining repeated work includes:

- 13 per-lap `GlobalRanking::mapRank(..., excludeLapId)` queries plus 13 preceding-lap lookups;
- five full `GlobalRanking::scores(..., excludeLapId)` passes, each loading the active lap history and eager-loading players/maps/servers;
- `RecordHistory::events()` replaying and hydrating the entire active lap history twice per homepage calculation;
- `MostActiveServer::scores()` issuing per-server lap, latest-lap, and 30/90-day player queries (N+1 growth with server count);
- separate live-stat and quick-stat aggregate queries;
- `loadHighlights()` being invoked via the site-wide `lap.submitted` Echo event, so every connected homepage visitor can trigger another 94-query/~1.5s calculation after each accepted lap.

**Recommended closure target:** cached/materialized homepage output or shared ranking/history snapshots, invalidated after accepted laps and server-status refreshes; alternatively, replace the per-lap exclusion calculations with a batched/single-pass historical algorithm. Target fewer than 20 queries and sub-500ms live TTFB before marking PERF-01 mitigated. Cache invalidation must preserve the existing live-update behavior rather than merely adding a long blind TTL.

**Preferred cache boundary:** cache the final small Home view-model (`highlights` plus `quickStats`), not `GlobalRanking::scores()` internally. `GlobalRanking::scores()` is a shared calculator with global, server-scoped, and per-`excludeLapId` variants; transparent internal caching would create high-cardinality historical keys, hide freshness semantics from unrelated callers, serialize a large 820-player/per-map structure into the database cache, and still leave Home's exclusion rankings, record-history replay, server activity calculation, and aggregate queries uncached. Keep the calculator pure. If current global/server ranking snapshots are later worth sharing across multiple pages, introduce an explicit cache/snapshot wrapper that caches only non-exclusion variants and has clear improvement-driven invalidation.

For the immediate Home fix, the active database cache is adequate because the final payload is small. Guard cold rebuilds with a distributed cache lock or stale-while-revalidate behavior so one accepted lap and many connected homepage clients do not all execute the 94-query rebuild simultaneously. Invalidate/rebuild after an accepted lap (and any other event that changes displayed Home data), then let the existing Echo listener read the shared result. A short TTL can be a safety fallback, but should not be the only freshness mechanism.

Reproducible live timing:

```bash
for i in 1 2 3 4 5 6 7 8; do
  curl -ksS -o /dev/null \
    -w "home $i ttfb=%{time_starttransfer} total=%{time_total}\n" \
    "https://redesign.hrl.effakt.info/?perf_audit=$i"
done
```

Reproducible query/time instrumentation from the project directory:

```bash
php artisan tinker --execute='$queries=[]; DB::listen(function ($q) use (&$queries) { $queries[]=$q->time; }); $start=microtime(true); (new App\Livewire\Home)->mount(); dump(["elapsed_ms"=>round((microtime(true)-$start)*1000,1), "queries"=>count($queries), "db_ms"=>round(array_sum($queries),1)]);'
```

#### Cache implementation follow-up — 8 July 2026

**Verdict: PERF-01 is mitigated for ordinary traffic, with a residual cold-rebuild concurrency risk.** The implementation follows the recommended narrow boundary: `Home` caches the final shared `highlights`/`quickStats` payload as one small database-cache value; `GlobalRanking` remains pure. `InvalidateHomeHighlightsCache` is auto-discovered and synchronously forgets the key on every `LapSubmitted`, while a ten-minute TTL provides fallback expiry. Feature tests now flush the array cache per test, cover warm reuse and event invalidation, and the installed event list confirms the listener registration.

Fresh independent measurements:

- cold rebuild after forgetting only `Home::CACHE_KEY`: **1,551.6ms**, **96 queries**, 159.1ms reported database time;
- immediate warm read: **0.9ms**, **one query**, with payload equality confirmed;
- eight live cache-hit requests: **38.7–74.0ms TTFB**, mean approximately **47.8ms**;
- full suite with deterministic webhook override: **144 tests / 361 assertions**; PHPStan, Pint, and `git diff --check` pass.

This clears the audit's user-facing sub-500ms/fewer-than-20-query warm-path target by a large margin. The previous 94-query computation remains the expected cold cost, now normally paid only after invalidation or expiry.

The implementation does **not** yet guarantee the documented “one rebuild per lap site-wide” behavior:

1. `Cache::remember()` performs no distributed lock around its callback. After `LapSubmitted` forgets the key and broadcasts to many connected homepage clients, several requests can miss together and each execute the 96-query/~1.55s rebuild (cache stampede).
2. If a lap invalidates the key while an older cold rebuild is already running, `Cache::forget()` can occur before that callback writes its result; the older request may then write a pre-lap snapshot back into the cache for up to ten minutes (invalidate-during-compute race).
3. `app/Listeners/InvalidateHomeHighlightsCache.php` is currently untracked. Tracked code depends on its invalidation behavior, so it must be committed before deployment.
4. The listener docblock says it runs inside an “already queued” `ProcessNewLap`, but `LapSubmissionController` invokes `ProcessNewLap::handle()` synchronously because the API response needs the ranking result. The cache forget is cheap and synchronous execution is appropriate; only the documentation claim is wrong.

Accordingly, `docs/performance.md`'s “Fully closed” wording is premature: the normal request latency is closed, while invalidation concurrency and stale-write correctness remain open.

**Recommended residual fix:** use a cache-generation/version key incremented on `LapSubmitted`, include that generation in the payload cache key, and guard each generation's cold calculation with a distributed cache lock plus a post-lock cache recheck. Generation keys prevent an old in-flight calculation becoming current after invalidation; the lock ensures only one request computes a new generation. If waiting clients must remain fast, serve the previous generation as stale while the new one rebuilds rather than making every client block.

Reproducible cache-hit timing:

```bash
for i in 1 2 3 4 5 6 7 8; do
  curl -ksS -o /dev/null \
    -w "home $i ttfb=%{time_starttransfer} total=%{time_total}\n" \
    "https://redesign.hrl.effakt.info/?cache_audit=$i"
done
```

A controlled stampede test after implementing the lock/version protection is:

```bash
php artisan tinker --execute='Cache::forget(App\Livewire\Home::CACHE_KEY);'
seq 1 5 | xargs -P5 -I{} \
  curl -ksS -o /dev/null -w "request {} %{time_starttransfer}\n" \
  'https://redesign.hrl.effakt.info/?stampede_audit={}'
```

Instrument/log the rebuild callback during that test; exactly one process should execute it for the new generation, and a lap arriving during a rebuild must cause the next read to use a newer generation rather than the completed older result.

#### Final PERF-01 revalidation — 8 July 2026

**Verdict: resolved; this supersedes the residual-risk verdict immediately above.** Commit `bb3355b` implements the recommended generation/version key, per-generation distributed cache lock, and post-lock cache recheck. `LapSubmitted` atomically initializes/increments the persistent generation counter. An in-flight old-generation calculation can now write only to an abandoned, expiring key; ordinary concurrent misses wait for the first rebuild and then consume its result. The five-second wait fallback deliberately computes uncached rather than failing if a lock holder exceeds the normal ~1.5-second cold calculation, preserving availability without allowing a stale generation to become current.

The listener is committed, its synchronous execution description is now accurate, and feature tests cover warm reuse, generation invalidation, and the stale-old-generation invariant. The current independent run passes **181 tests / 506 assertions**, PHPStan (single-process/debug mode), Pint, the production build, and `git diff --check`. A fresh live cache-hit request returned **62ms TTFB**; prior repeated live samples were ~39–74ms. This meets the audit's fewer-than-20-query/sub-500ms normal-path closure target by a wide margin.

True multi-process contention has not been instrumented under load, so production monitoring should still watch rebuild latency and lock timeouts. That is operational assurance, not a reason to keep PERF-01 open: the identified stampede and stale-write defects are now addressed in the implementation.

### PERF-02 — Missing indexes (Resolved)

**Revalidation (2026-07-08): resolved.** Migration batches 21 and 22 are deployed with indexes selected from real query/`EXPLAIN` evidence:

- `lap_times(server_id, map_id, player_id, time)` — the webhook's best-time `MIN(time)` now reports `Select tables optimized away`, and the grouped leaderboard-position lookup can use the covering index;
- `lap_times(server_id, created_at)` — latest-server-lap queries select this index with a backward scan rather than filesort;
- unique `players(hash, name)` — the hot hash-prefix lookup changed from a full scan to `ref`, while the complete identity lookup is `const`; this also closes the concurrent player-creation race without falsely requiring hash alone to be unique.

Fresh schema inspection confirms all deployed definitions and zero composite player-identity collisions. Existing SEC-01/SEC-04 work separately provides unique server and map identities. The full suite passes at **181 tests / 506 assertions**; PHPStan in single-process/debug mode, Pint, and `git diff --check` pass.

Candidate map/server leaderboard indexes were tested but correctly not retained: MySQL's semijoin plan for `whereHas('server')` bypassed them, while the current filesorts cover only roughly 162 rows and are not a measured bottleneck. Rewriting five ranking/history call sites solely to force index use would add behavioral risk without current performance evidence. Revisit when timings or data growth justify it.

The original suggestion of making `players.hash` alone unique is permanently withdrawn because it conflicts with the real protocol. Composite `(hash,name)` uniqueness implements the confirmed identity rule safely. Remaining small leaderboard filesorts are documented monitoring candidates, not unresolved PERF-02 work; no speculative indexes or five-call-site query rewrite should be added until measurements justify them.

**Release hygiene:** both PERF-02 migration files and `tests/Feature/PerformanceIndexesTest.php` are still untracked, while the player lookup/retry changes in `ProcessNewLap.php` are uncommitted. Commit the complete set before any tracked-files-only deployment; the live database already contains batches 21/22, so deploying only the old tracked application code would leave schema and lookup semantics out of sync.

### PERF-03 — Full result computation before pagination (Resolved)

**Revalidation (2026-07-08): resolved.** `/api/v1/maps/{map}/leaderboard` now accepts `page`/`per_page`, defaults to 50 entries, caps `per_page` at 100, and returns Laravel's standard `data`/`links`/`meta` envelope. A fresh live request for page 2 with two entries preserved global ranks 3/4 and reported the correct 188-entry total. Tests cover page slicing/rank preservation, maximum page size, and extreme page input.

This intentionally bounds response/transfer size only. `GlobalRanking::mapLeaderboard()` still ranks the complete qualifying set before `array_slice()`, as do the equivalent Livewire leaderboards. Independent measurements reproduce the documented baseline:

- 1,668 laps and 691 ranked players;
- full `GlobalRanking::scores()`: **197.4ms**;
- largest map: 188 distinct players, **22.7ms** to rank.

Concrete revisit triggers are now documented: global scoring above ~750ms or 25,000 laps; a map above 1,000 distinct players or ~150ms; Players List above ~1s. Deferring a SQL/materialized-snapshot rewrite until one of those thresholds is reached is proportionate to current scale.

**Extreme-page regression closed:** the controller now computes the real last page from the completed ranking and clamps the requested page before multiplying it into an array offset. This keeps the offset small and integer-valued even for arbitrarily large input. The dedicated regression test passes, and a fresh live request using `page=999999999999999999999&per_page=2` returned HTTP 200 in approximately 102ms instead of throwing `TypeError`/500. The explicit policy is normalization: pages below 1 become page 1 and pages above the available range become the last real page.

The response-shape change remains under API `v1`. This is technically breaking but was explicitly accepted because the endpoint is staging-only with no known consumers; `data` entries themselves retain their prior shape. Full-set ranking remains intentionally deferred until the documented thresholds are reached, so it is a monitored design decision rather than open PERF-03 work.

**Release hygiene:** the controller, API tests, and API/performance documentation are uncommitted. Include them together in the release so the published response contract and deployed behavior cannot diverge.

### PERF-04 — Deployment caching (Resolved)

**Revalidation (2026-07-08): fingerprinted-asset caching is resolved by Nginx Proxy Manager.** Fresh live probes show content-hashed JS, CSS, and WOFF2 assets return exactly `Cache-Control: public, max-age=31536000, immutable`, alongside stable ETags.

The cache rule is scoped safely: dynamic homepage HTML still returns `max-age=0, must-revalidate, no-cache, no-store, private` with session cookies, and the plaintext API still returns `no-cache, private`. No evidence was found of NPM accidentally caching dynamic responses.

This meets the original hashed-asset browser-cache target. NPM does not expose a cache-hit header, so these probes verify downstream browser behavior rather than whether a particular response body came from NPM's disk cache; either way, repeat browser visits can reuse the content-addressed files for one year without revalidation.

- Content-hashed JS/CSS/font assets now have long-lived immutable caching.
- HTML correctly uses private/no-store semantics, but every anonymous page creates session/XSRF cookies, reducing opportunities for shared page caching.
- Laravel config, events, routes, and views are all cached, as verified through the active application runtime on 8 July 2026.
- The frontend build is ~1.1 MB on disk. It includes many font formats/subsets and weights; the main JS is 88.6 KB and CSS is 59.8 KB before compression.

The versioned Livewire JavaScript route is outside `/build/assets/*` and currently advertises one-year public caching without `immutable`; its query-string version identifier makes that reasonable, although it also exposes PHP's version as noted under SEC-05. PERF-04 is now resolved. As lower-priority follow-up optimization, review whether anonymous read-only GETs need sessions and measure the unusually broad font preload/waterfall before pruning formats/weights. Deployment automation should rebuild Laravel's caches after releases and configuration changes.

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

**Follow-up (2026-07-07): resolved.** `VITE_REVERB_HOST`/`PORT`/`SCHEME` were aliases of the server-side `REVERB_*` vars (correctly `localhost` for Laravel-to-Reverb loopback publishing, wrong for the public bundle) — decoupled into their own literal values (`redesign.hrl.effakt.info`, `443`, `https`) and rebuilt; confirmed the built bundle now references the real hostname. The Reverb server process also wasn't running at all for this app — started (not yet made durable via systemd/supervisor, see OPS-01, still open). The proxy layer turned out to be an nginx reverse proxy (Nginx Proxy Manager) in front of FastPanel, not FastPanel's own nginx directly — the user added a `/app` custom location in NPM forwarding to this container's `192.168.88.54:8081` with WebSocket support enabled. Verified end-to-end: `curl` against `https://redesign.hrl.effakt.info/app/<key>` with WebSocket upgrade headers now returns `101 Switching Protocols` (both the user's report and independently from this environment).

**Revalidation (2026-07-08): resolved for the original client/proxy defect.** The homepage currently serves `app-CKqyMCrz.js`; that asset contains the public hostname and no `localhost`/`8081`, and a fresh public upgrade again returned `101 Switching Protocols`. This does not close OPS-01's service supervision/restart concern or replace a true browser event-delivery smoke test.

**PageSpeed Insights WebSocket follow-up (2026-07-08): REL-01 remains resolved.** PageSpeed Insights successfully loads the website but its Lighthouse runner logs `ERR_NAME_NOT_RESOLVED` only for the JavaScript-opened WSS connection to the same hostname. Public DNS checks against Cloudflare (`1.1.1.1`) and Google (`8.8.8.8`) both return `redesign.hrl.effakt.info A 114.23.254.181`; bypassing DNS against that public IP returns `101 Switching Protocols` for the exact `/app/<key>?protocol=7...` URL. This proves the public DNS record, TLS, Nginx Proxy Manager, FastPanel routing, and Reverb endpoint work outside PageSpeed's lab path. Treat the console entry as a PageSpeed-runner limitation unless a real browser on an unrelated external network reproduces it. Do not add a speculative AAAA record or change the working WSS hostname solely to silence this synthetic-runner error; consider lazy/conditional realtime startup only if the failed lab connection materially affects an audit score and that behavior is separately tested in real browsers.

**Ineffective Lighthouse workaround removed (2026-07-08).** The temporary `try/catch` around `new Echo(...)` could not catch asynchronous DNS/WebSocket failures and hid only synchronous setup errors. Echo initialization is direct again and the production build passes. PageSpeed's lab-only DNS warning remains informational under the verified public DNS/WSS evidence above; use explicit connection-state handling or conditional realtime startup only if a real requirement emerges.

#### In-progress live-update changes review — 8 July 2026

The current uncommitted change from `echo-public:...` to `echo:...` is correct for the installed Livewire 4 Echo bridge: its client parser maps the `echo:` signature to `window.Echo.channel(...)`, while custom `broadcastAs()` names correctly use the leading-dot listener form. Parameterless wrapper handlers on model-loading methods also avoid broadcast payloads being injected into model-typed parameters.

`ServerStatusRefreshed` closes a genuine stale-status gap by broadcasting once after the scheduled server query pass, and retargeting map leaderboard components to `LapSubmitted` keeps total-attempt counters fresh after non-PB laps. The complete current suite passes at 142 tests / 357 assertions with the explicit webhook test-environment override; PHPStan, Pint, and `git diff --check` pass.

Two follow-ups remain:

- `app/Events/ServerStatusRefreshed.php` is untracked while committed/tracked code already imports it; include it in the eventual commit or the scheduled command will fail after a tracked-files-only deployment.
- Map and server-map leaderboard pages now reload their database-backed ranking after every lap anywhere on the site. This is functionally correct but creates global fan-out; use the event's `server_id`/`map_id` payload to skip irrelevant reloads if real traffic makes this measurable. Current tests call handlers directly and do not prove an actual browser receives and processes either broadcast, so the browser event-delivery smoke test remains part of TEST-01.

### OPS-01 — Background service deployment (Resolved)

**Revalidation (2026-07-08): resolved.** Hosting-account Supervisor definitions now manage:

- one Reverb process with automatic restart;
- eight queue workers with three attempts and graceful group shutdown;
- one persistent `schedule:work` process with automatic restart.

All commands use the PHP 8.4 binary, the correct project path, the application hosting user, and dedicated logs. Fresh scheduler output shows `app:refresh-live-server-info` completing every minute, while fresh worker output shows each resulting `ServerStatusRefreshed` broadcast job completing in roughly 2–4ms. Public WSS/Pusher checks remain successful. Earlier Reverb `EADDRINUSE` entries came from the one-time transition away from a manually started process; subsequent logs show the Supervisor-managed server starting normally.

Continue normal operational monitoring for crash loops, failed jobs, queue age, and scheduler freshness. The Supervisor definitions live outside this Git repository, so deployment documentation remains the durable handoff for recreating them on another host.

### Logs and health checks

- Access logs contained 47 HTTP 500 responses among roughly 29,700 recorded responses (~0.16%), concentrated on core routes during active development. Recent visible causes included a missing view, Reverb port conflict, and temporary database connection failures.
- `/up` returned 200 but is Laravel's shallow built-in health route; it does not demonstrate DB, queue, scheduler, or Reverb health.
- Operational docs acknowledge that queued broadcasting silently fails without a worker.

**Recommendation:** expose separate dependency-aware readiness checks and alert on 5xx rate, queue age/failures, scheduler freshness, and WebSocket connectivity.

## Tests and code quality

### Passing checks

| Check | Result |
|---|---|
| Pest | **181 passed, 506 assertions** (with `WEBHOOK_HRL_VERIFY_ENFORCE=false`) |
| PHPStan/Larastan level 5 | **0 errors** |
| Pint (`--test`) | Passed |
| PHP syntax | Passed for app, routes, config, migrations, factories, and tests |
| Composer audit | No advisories |
| npm production dependency audit | No advisories |

The tests give meaningful coverage to rankings, record history, server activity, lap submission behaviour, API responses, live-update listener logic, routes, splits, and live-server refresh logic.

### TEST-01 — Important coverage/enforcement gaps (Medium)

- No test verifies either API rate limiter returns 429 at the correct boundary.
- API pagination covers normal pages and oversized `per_page`, but not an extreme `page` value that overflows the slice offset.
- Webhook validation now covers split ceilings, numeric extremes, positive durations, duplicate/gapped checkpoint IDs, out-of-order valid IDs, and compare-and-set baseline behavior. It still lacks a real Lua/game-memory integration test for Any Order/Rally checkpoint production and a true concurrent variant-cap test.
- No real UDP client integration test exists; it is always faked.
- No browser/E2E coverage exists for Alpine modals, keyboard use, responsive navigation, real Echo/Reverb transport, or JavaScript console errors.
- `PlayerShow` has only route smoke coverage; its global-only favourite-server/display logic lacks focused tests.
- No coverage percentage or mutation testing is produced, so 176 passing tests should not be interpreted as comprehensive coverage.
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

1. Smoke-test real Any Order and Rally laps end-to-end; confirm Lua emits a permutation of `1..N`. Harden the suffix-based map namespace and transactional variant cap afterward.
2. Run a real browser event-delivery smoke test, including Livewire/Echo listener wiring.
3. Exercise the report-only CSP across representative interactions and enforce it; add a report collector if retaining an observation period.
4. Review font preloading and anonymous session creation; revisit leaderboard query plans only when PERF-03's documented measurements justify a rewrite.
5. Add deterministic test environment configuration plus CI, abuse-case, browser, accessibility, and real-time transport tests.
6. Monitor the accepted FastPanel DB-privilege risk and refresh stale documentation/tooling baselines; no DB privilege change is currently planned.

## Audit limitations

- This was not a destructive penetration test; no forged lap was submitted and rate limits were not exhausted.
- No authenticated functionality exists to assess.
- No Lighthouse/browser automation, screen reader, load test, or external port scan was run.
- Host-level OpenResty/nginx-compatible, Supervisor, and cron configuration was only partially readable; process state was isolated by the sandbox.
- Dependency advisory results reflect registries at the audit time only.
- Performance figures are a small observational sample, not a controlled load test.
