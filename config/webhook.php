<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lap submission HRL query verification (SEC-01, docs/security.md)
    |--------------------------------------------------------------------------
    |
    | Before recording a submitted lap, the webhook can cross-check it against a live UDP
    | \query response from the same ip:port the HTTP request came from — requiring the game
    | server's Lua/SAPP script to publish a few HRL-specific query_add fields (hrl_enabled,
    | hrl_protocol, hrl_token) alongside the standard ones. See docs/security.md.
    |
    | `enabled` turns the check on at all. `enforce` controls what happens when it fails: false
    | (the default) only logs a warning and still records the lap — necessary while real game
    | servers are still running Lua scripts that predate this feature and don't publish the new
    | fields yet. Flip to true only once every active server's script has been updated, or a
    | legitimate server will have every submission rejected.
    |
    | `timeout_seconds` was lowered from 2s to 1s (SEC-01 audit follow-up) — with one retry, a
    | fully-unresponsive source still occupies a PHP worker for up to ~2s per request either way,
    | but halving the per-attempt wait meaningfully lowers worst-case exhaustion.
    |
    */
    'hrl_query' => [
        'enabled' => env('WEBHOOK_HRL_VERIFY_ENABLED', true),
        'enforce' => env('WEBHOOK_HRL_VERIFY_ENFORCE', false),
        'supported_protocol' => '1',
        'timeout_seconds' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency cache lifetimes (SEC-01 audit follow-up, docs/security.md)
    |--------------------------------------------------------------------------
    |
    | Two separate windows, not one shared value — a single 10s window was too short for both
    | jobs it was doing: `processing_reservation_seconds` only needs to outlast the slowest
    | realistic single request (UDP verification + DB work), but `result_retention_seconds` needs
    | to outlast how long a real client might plausibly wait before retrying a request whose
    | response it never saw. A durable per-(server, submission_id) unique DB constraint on
    | `lap_times` (see the add_submission_id_to_lap_times_table migration) is the actual source of
    | truth against a duplicate lap ever being recorded twice — these cache windows only control
    | how long the exact original *response* can still be replayed verbatim rather than
    | recomputed from current state.
    |
    */
    'processing_reservation_seconds' => env('WEBHOOK_PROCESSING_RESERVATION_SECONDS', 30),
    'result_retention_seconds' => env('WEBHOOK_RESULT_RETENTION_SECONDS', 300),

    /*
    |--------------------------------------------------------------------------
    | Webhook rate limiting (SEC-01 audit follow-up, docs/security.md)
    |--------------------------------------------------------------------------
    |
    | Two tiers: a source starts out "unverified" (the strict tier) and only earns the more
    | generous "verified" tier after a request from that exact ip:port completes a real,
    | successful HRL query verification — cached at `verified-webhook-source:{ip}:{port}` for
    | `verified_marker_ttl_seconds`. A source must still pass verification on every single
    | request regardless of tier; the marker only ever selects *how much traffic* it's allowed,
    | never skips the check itself.
    |
    | Within each tier, THREE limits apply together, not one instead of another: a per-second
    | burst allowance (players finishing within the same second of each other), a per-`ip:port`
    | per-minute sustained allowance (the submitted server identity), and a coarser per-IP
    | per-minute ceiling that a caller can't evade just by rotating the (unverified, at this
    | layer) `port` value on every request — this last one applies at both tiers specifically so
    | running many ports can't bypass it even once "verified."
    |
    | These starting values assume real per-server lap rates are far below even the unverified
    | ceiling in normal play — sized to allow a burst (several players finishing together) while
    | keeping the sustained allowance conservative. Revisit once real traffic patterns are known;
    | see docs/security.md.
    |
    */
    'rate_limit' => [
        'unverified' => [
            'burst_per_second' => env('WEBHOOK_RATE_LIMIT_UNVERIFIED_BURST', 2),
            'per_ip_per_minute' => env('WEBHOOK_RATE_LIMIT_UNVERIFIED_PER_IP', 30),
            'per_ip_port_per_minute' => env('WEBHOOK_RATE_LIMIT_UNVERIFIED_PER_IP_PORT', 15),
        ],
        'verified' => [
            'burst_per_second' => env('WEBHOOK_RATE_LIMIT_VERIFIED_BURST', 10),
            'per_ip_per_minute' => env('WEBHOOK_RATE_LIMIT_VERIFIED_PER_IP', 300),
            'per_ip_port_per_minute' => env('WEBHOOK_RATE_LIMIT_VERIFIED_PER_IP_PORT', 120),
        ],
        'verified_marker_ttl_seconds' => env('WEBHOOK_VERIFIED_MARKER_TTL', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Split/checkpoint bounds (SEC-04 audit follow-up, docs/security.md)
    |--------------------------------------------------------------------------
    |
    | A hard, protocol-wide ceiling on how many distinct checkpoints one submission can claim —
    | enforced in StoreLapTimeRequest regardless of which map it's for. Real maps top out at 21
    | (see docs/database.md); 30 leaves generous headroom for a legitimate map while still
    | bounding the resource-exhaustion risk an unauthenticated, unbounded `splits` array would
    | otherwise allow. Below this ceiling, App\Jobs\ProcessNewLap separately learns and enforces
    | each individual map's own real checkpoint count (see the add_checkpoint_count_to_maps_table
    | migration) — this value only ever needs raising if a real map genuinely has more than 30
    | checkpoints, which no map observed so far does. (Raised from 20 to 30 on 2026-07-14 after a
    | real 21-checkpoint map was found — the previous ceiling would have rejected it outright.)
    |
    */
    'max_checkpoints' => env('WEBHOOK_MAX_CHECKPOINTS', 30),

    /*
    |--------------------------------------------------------------------------
    | Lap time ceiling (SEC-04 audit follow-up, docs/security.md)
    |--------------------------------------------------------------------------
    |
    | `player_time` had no upper bound at all. Real laps range 15.3s-1170.9s (~19.5 min, see
    | docs/database.md) — 3600s (1hr) leaves 3x headroom for a genuinely long real race while
    | still bounding the field. Each split's `duration` is separately required to be
    | `<= player_time` (StoreLapTimeRequest) rather than getting its own ceiling here — checked
    | against real data first: zero real splits ever exceed their own lap's total time, so that
    | relational rule is both correct and self-tuning as this value changes.
    |
    | Deliberately NOT applied to `startTime`/`endTime`: real data shows these aren't reliably
    | lap-relative across different Lua script versions in the wild (some rows use small
    | relative-looking values, many use large absolute server-clock-like values up to a literal
    | 999999.99 sentinel) — and neither field feeds any real leaderboard/comparison logic today
    | (only `duration` does, via LapTimeSplit::compare()). A tight bound here would reject real,
    | already-accepted submissions for no functional benefit; they only get a generous overflow
    | guard instead (see StoreLapTimeRequest).
    |
    */
    'max_lap_time_seconds' => env('WEBHOOK_MAX_LAP_TIME_SECONDS', 3600),

    /*
    |--------------------------------------------------------------------------
    | Map checkpoint-variant cap (SEC-04 review follow-up, docs/security.md)
    |--------------------------------------------------------------------------
    |
    | `App\Jobs\ProcessNewLap::resolveMap()` forks a mismatched checkpoint count into its own
    | `{map_name}-splits-{count}` Map row rather than rejecting it or corrupting the original
    | map's baseline — maps are only ever added, never redesigned in place. This bounds how many
    | such forks one base map name can accumulate before a further mismatched submission is
    | rejected outright instead of creating yet another variant. A handful of genuinely distinct
    | courses sharing one map file is plausible; an unbounded number is more likely abuse or a
    | corrupted client than real level design — no real map has ever needed more than one variant
    | so far (see docs/database.md).
    |
    */
    'max_map_variants_per_name' => env('WEBHOOK_MAX_MAP_VARIANTS_PER_NAME', 3),

    /*
    |--------------------------------------------------------------------------
    | NAT internal-IP rewrite (ported from ApiController.php-legacy)
    |--------------------------------------------------------------------------
    |
    | The old hosting site's UniFi router sometimes resolved the submitting game server's IP as
    | one of the router's own internal addresses instead of the real public IP, for reasons
    | specific to that router's NAT/reflection behavior. The legacy app hardcoded a rewrite for
    | this in `ApiController::newTime()`/`claimPlayer()`; this config-driven equivalent maps each
    | known internal address straight to the real public IP it actually stands for, applied
    | before the ip is used for idempotency keys, rate limiting, verification, or storage. Add
    | entries here (rather than in code) if another address needs the same treatment.
    |
    */
    'internal_ip_map' => [
        '192.168.88.1' => '114.23.254.181',
        '192.168.88.99' => '114.23.254.181',
        '192.168.88.234' => '114.23.254.181',
    ],

];
