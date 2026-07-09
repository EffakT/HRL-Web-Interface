<?php

// SEC-06 audit follow-up (docs/security.md) — config/reverb.php app policy.

it('restricts the reverb app to the real origin (plus 127.0.0.1 for the real Echo/Reverb browser test), with connection limits and rate limiting enabled', function () {
    $app = config('reverb.apps.apps.0');

    // '127.0.0.1' added 2026-07-09 (code review follow-up) so tests/Browser/EchoReverbDeliveryTest.php
    // can pass — Pest's browser plugin always serves pages from its own local 127.0.0.1:<port>
    // origin, never the real domain. See config/reverb.php's own comment for the risk assessment.
    expect($app['allowed_origins'])->toBe(['redesign.hrl.effakt.info', '127.0.0.1'])
        ->and($app['max_connections'])->toBe(500)
        ->and($app['rate_limiting']['enabled'])->toBeTrue()
        ->and($app['rate_limiting']['terminate_on_limit'])->toBeTrue();
});
