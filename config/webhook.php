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
    | An identical (ip, port, player, map, time, token) payload arriving again within this many
    | seconds is rejected outright rather than recorded a second time — cheap protection against
    | a naive replay or a Lua-side retry-on-timeout resubmitting the same lap.
    |
    */
    'duplicate_window_seconds' => 10,

];
