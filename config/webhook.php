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

];
