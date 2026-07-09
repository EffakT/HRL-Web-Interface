<?php

use App\Helpers\GameServerQuery;
use App\Jobs\ProcessNewLap;
use Illuminate\Contracts\Console\Kernel;

/**
 * TEST-01 audit follow-up (2026-07-09) — a standalone worker (not a Pest test file, not
 * autoloaded by any testsuite) that triggers one real, disposable lap submission directly
 * through `ProcessNewLap` against the REAL `redesign_hrl` production database, so the resulting
 * `LapSubmitted`/`LeaderboardUpdated` broadcasts go out through the real, supervisor-managed
 * queue worker and Reverb instance (OPS-01) — the only way to prove a real browser watching the
 * real live site actually receives a real pushed WebSocket update via Echo/Livewire, since the
 * page a Pest browser test visits is served by that same real running app, not this test
 * process's own sqlite-backed instance. Explicitly accepted (per user decision, 2026-07-09):
 * this briefly broadcasts a real, obviously-fake event to any real visitor's open browser tab —
 * bypasses the public HTTP webhook (and its SEC-01 HRL verification, which `enforce=true` in
 * this environment would otherwise reject outright for a non-real game server) by calling
 * `ProcessNewLap` directly with `liveQueryResponse: false`, which skips the live UDP query
 * entirely (see `resolveHostname()`'s docblock) rather than attempting one against a fake ip:port.
 *
 * Usage: php trigger_real_lap_broadcast.php <outputFile>
 * Writes a JSON line to $outputFile: {"ok": true, "mapName": ..., "playerHash": ..., "ip": ...,
 * "port": ...} on success, or {"ok": false, "error": "..."} on failure — the Pest test uses this
 * to clean up the exact disposable rows created, and to know what to wait for in the browser.
 */

require __DIR__.'/../../../vendor/autoload.php';

[$script, $outputFile] = $argv;

$app = require __DIR__.'/../../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$suffix = bin2hex(random_bytes(6));
$mapName = "__browser_echo_test_{$suffix}";
$playerHash = "__browser_echo_test_hash_{$suffix}";
$ip = '203.0.113.'.random_int(1, 254); // TEST-NET-3 (RFC 5737) — guaranteed non-routable, never a real server.
$port = random_int(20000, 29999);

try {
    $job = new ProcessNewLap(
        ip: $ip,
        port: $port,
        data: [
            'map_name' => $mapName,
            'player_hash' => $playerHash,
            'player_name' => 'Automated Browser Test',
            'player_time' => 42.0,
            'race_type' => 0,
            'submission_id' => "browser-echo-test-{$suffix}",
            'hrl_token' => null,
            'splits' => null,
        ],
        liveQueryResponse: false,
    );

    $job->handle($app->make(GameServerQuery::class));

    file_put_contents($outputFile, json_encode([
        'ok' => true,
        'mapName' => $mapName,
        'playerHash' => $playerHash,
        'ip' => $ip,
        'port' => $port,
    ]));
} catch (Throwable $e) {
    file_put_contents($outputFile, json_encode(['ok' => false, 'error' => $e::class.': '.$e->getMessage()]));
}
