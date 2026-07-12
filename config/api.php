<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public read API rate limit (TEST-01 audit follow-up, docs/api.md)
    |--------------------------------------------------------------------------
    |
    | Requests per minute per IP for the `api` named limiter (AppServiceProvider), covering
    | /api/v1/servers, /api/v1/maps/{map}/leaderboard, and /api/v1/laps/{lapTime}. Not a measured
    | value — a starting point, revisit if real usage says otherwise. Pulled into config (rather
    | than hardcoded in the limiter closure) so a test can assert the real production ceiling
    | directly instead of only exercising the middleware wiring under a substitute value.
    |
    */

    'rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 60),

];
