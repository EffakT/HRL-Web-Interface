<?php

// SEC-06 audit follow-up (docs/security.md) — config/reverb.php app policy.

it('restricts the reverb app to the real origin, with connection limits and rate limiting enabled', function () {
    $app = config('reverb.apps.apps.0');

    expect($app['allowed_origins'])->toBe(['redesign.hrl.effakt.info'])
        ->and($app['max_connections'])->toBe(500)
        ->and($app['rate_limiting']['enabled'])->toBeTrue()
        ->and($app['rate_limiting']['terminate_on_limit'])->toBeTrue();
});
