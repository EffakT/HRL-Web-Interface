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
    */
    'hrl_query' => [
        'enabled' => env('WEBHOOK_HRL_VERIFY_ENABLED', true),
        'enforce' => env('WEBHOOK_HRL_VERIFY_ENFORCE', false),
        'supported_protocol' => '1',
        'timeout_seconds' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Duplicate submission window
    |--------------------------------------------------------------------------
    |
    | An identical (ip, port, player, map, time, token) payload — or, when the client sends one,
    | a repeated `submission_id` — arriving again within this many seconds is treated as a retry:
    | the ORIGINAL response is replayed rather than the lap being recorded a second time or the
    | retry simply failing. See LapSubmissionController::store()'s idempotency handling.
    |
    */
    'duplicate_window_seconds' => 10,

    /*
    |--------------------------------------------------------------------------
    | Webhook rate limiting (SEC-01 audit follow-up, docs/security.md)
    |--------------------------------------------------------------------------
    |
    | Two limits apply together, not one instead of the other. `per_ip_port` alone can be
    | trivially bypassed by an attacker rotating the (attacker-supplied, unverified at this
    | layer) `port` value on every request to get a fresh allowance each time, while still
    | forcing a real UDP query attempt per request. `per_ip` is the ceiling that rotation can't
    | evade — set generously enough that one host legitimately running several distinct game
    | servers isn't throttled by its neighbors' traffic.
    |
    */
    'rate_limit' => [
        'per_ip_per_minute' => env('WEBHOOK_RATE_LIMIT_PER_IP', 600),
        'per_ip_port_per_minute' => env('WEBHOOK_RATE_LIMIT_PER_IP_PORT', 120),
    ],

];
