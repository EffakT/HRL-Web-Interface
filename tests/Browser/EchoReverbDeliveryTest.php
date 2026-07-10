<?php

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

/**
 * `DB::connection()` in this Pest process resolves to whatever the default connection is here —
 * sqlite, forced by phpunit.xml — not the real `redesign_hrl` database the trigger script wrote
 * to. Cleanup needs its own direct real-MySQL connection, same pattern as
 * `MapVariantCapConcurrencyTest.php`'s `mysqlDirect()`, just pointed at the real database
 * deliberately instead of the disposable test one.
 */
function productionMysqlDirect(): Connection
{
    config(['database.connections.production_mysql_direct' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => 'redesign_hrl',
        'username' => env('DB_USERNAME'),
        'password' => env('DB_PASSWORD'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ]]);

    return DB::connection('production_mysql_direct');
}

/**
 * TEST-01 audit follow-up (2026-07-09) — the one remaining real gap the rest of the test suite
 * can't close: neither calling a Livewire listener method directly (skips Echo entirely) nor the
 * earlier manual standalone `pusher-js` script (bypassed Echo/Livewire on purpose, see
 * decisions.md) actually proves a real browser's Livewire `#[On('echo:...')]` attribute receives
 * a real pushed WebSocket event and re-renders. This does, end-to-end: a real Pest browser visits
 * the real live homepage, a real disposable lap submission is triggered directly through
 * `ProcessNewLap` (bypassing the public webhook's SEC-01 HRL verification, which `enforce=true`
 * here would otherwise reject for a non-real game server — see the trigger script's docblock)
 * against the REAL `redesign_hrl` database, and the real supervisor-managed queue worker +
 * Reverb instance (OPS-01) push `LapSubmitted` out for real — the browser is watching for its
 * Quick Stats "LAPS" count to increment via Livewire's own reactive re-render, no page reload.
 *
 * Deliberately touches real production infrastructure (explicit user decision, 2026-07-09,
 * over two safer-but-heavier alternatives — see docs/testing.md): this briefly broadcasts one
 * real, obviously-fake event to any real visitor's open browser tab, and writes to (then cleans
 * up from) the real live database. Gated behind `ALLOW_LIVE_BROADCAST_TEST=true` rather than
 * running by default under `composer test`/CI, unlike every other test in this suite — there's
 * no "missing creds" signal to gate on here the way the disposable-test-DB concurrency test has
 * (this DB's credentials are the app's own primary connection, always present), so an explicit
 * opt-in is the only way to keep this out of an unaware default run.
 */
it('delivers a real lap submission to a real browser via Echo/Livewire/Reverb, live', function () {
    // Pest's browser plugin doesn't actually navigate to an external URL — `LaravelHttpServer`
    // (confirmed by reading its source, 2026-07-09) intercepts every `visit()` and dispatches the
    // request through THIS test process's own in-process Laravel kernel, rewriting even an
    // absolute https:// URL to its own local socket. That's fine for the page render itself, but
    // it means this test process's own DB connection — sqlite, forced by phpunit.xml — is what
    // actually serves both the initial page AND Livewire's subsequent AJAX re-render requests
    // (Livewire's JS targets the page's own origin, which `rewrite()` already pointed at this
    // local server). The browser's actual WebSocket connection is unaffected by any of this — the
    // already-built JS bundle has the real production Reverb host/port/key baked in from its own
    // `npm run build`, so it connects for real regardless of which process rendered the HTML.
    // Overriding the default DB connection here, for this one test, is what makes the *data* the
    // rendered page/Livewire re-render sees actually real too. Restored in `finally` — every
    // other test in this same `artisan test` process run must keep seeing sqlite, not this.
    $originalDefaultConnection = config('database.default');
    $originalMysqlConfig = config('database.connections.mysql');
    $originalCacheDefault = config('cache.default');

    config(['database.default' => 'mysql']);
    config(['database.connections.mysql.host' => env('DB_HOST', '127.0.0.1')]);
    config(['database.connections.mysql.port' => env('DB_PORT', '3306')]);
    config(['database.connections.mysql.database' => 'redesign_hrl']);
    config(['database.connections.mysql.username' => env('DB_USERNAME')]);
    config(['database.connections.mysql.password' => env('DB_PASSWORD')]);
    DB::purge('mysql');

    // Home's highlights (PERF-01) are cached and invalidated by a generation key bumped in
    // whatever `CACHE_STORE` the invalidating process uses. phpunit.xml forces `array` (process-
    // local) here — the trigger script's own childEnv override (below) points it at the real
    // `database` store instead, so this process's reads need to match, or a genuine Echo/Livewire
    // re-render would still serve a stale pre-lap snapshot from this process's own isolated cache.
    config(['cache.default' => 'database']);

    $page = visit(config('app.url'));
    $page->assertNoJavascriptErrors();

    $lapsText = $page->text('[data-testid="quick-stats-laps"]');
    preg_match('/([\d,]+)/', (string) $lapsText, $matches);
    $lapsBefore = (int) str_replace(',', '', $matches[1] ?? '0');

    // Broadcasts aren't replayed to late subscribers — triggering the lap before Echo has
    // actually finished subscribing to the `activity` channel would miss the event entirely,
    // even though Echo itself reports "connected" moments later. Poll for real subscription
    // confirmation first (code review follow-up, 2026-07-09).
    $subscribed = false;
    $subscribeDeadline = microtime(true) + 10;
    while (microtime(true) < $subscribeDeadline) {
        $isSubscribed = $page->script(<<<'JS'
            () => window.Echo?.connector?.pusher?.channel('activity')?.subscribed === true
            JS);

        if ($isSubscribed) {
            $subscribed = true;
            break;
        }

        Sleep::usleep(250_000);
    }

    expect($subscribed)->toBeTrue('Echo never finished subscribing to the activity channel within 10s.');

    // Diagnostic (code review follow-up, 2026-07-09): binds our own raw listener directly, so we
    // can tell "Echo never received the message at all" apart from "Echo received it but
    // Livewire's own listener wiring/re-render didn't fire" when this test fails.
    $page->script(<<<'JS_WRAP'
    () => {
        window.__echoReverbTestReceived = null;
        window.Echo.channel('activity').listen('.lap.submitted', (e) => {
            window.__echoReverbTestReceived = JSON.stringify(e);
        });
    }
    JS_WRAP);

    $outputFile = tempnam(sys_get_temp_dir(), 'echo_reverb_');
    $script = __DIR__.'/support/trigger_real_lap_broadcast.php';

    // Override phpunit.xml's sqlite/null-broadcast/sync-queue forcing back to the REAL
    // connections for this one child process, deliberately — the point is to hit the real DB
    // and real broadcast/queue infra the real live site uses, not this Pest process's own
    // disposable :memory: DB and disabled broadcasting. Missing BROADCAST_CONNECTION/
    // QUEUE_CONNECTION here was a real bug (code review follow-up, 2026-07-09): every prior
    // attempt via this test silently ran with broadcasting fully disabled — the DB writes still
    // worked (DB_* was overridden), so nothing looked wrong until the worker log was checked and
    // showed no new activity across several runs. Only a raw manual shell invocation (not
    // through this test, no phpunit.xml env inherited) had ever actually broadcast anything.
    $childEnv = array_merge(getenv(), [
        'DB_CONNECTION' => 'mysql',
        'DB_DATABASE' => 'redesign_hrl',
        'BROADCAST_CONNECTION' => 'reverb',
        'QUEUE_CONNECTION' => 'database',
        // Also real (code review follow-up, 2026-07-09): Home's highlights are cached
        // (PERF-01) and invalidated via a generation key bumped by a listener on this same
        // LapSubmitted event. phpunit.xml forces CACHE_STORE=array — process-local, in-memory
        // — so without this override the trigger script's invalidation would happen in its own
        // short-lived process's cache, never reaching the page-serving process's real cache at
        // all, leaving it serving a stale pre-lap snapshot even though Echo/Livewire's listener
        // genuinely fired.
        'CACHE_STORE' => 'database',
    ]);

    $result = null;

    try {
        $proc = proc_open(['php', $script, $outputFile], [], $pipes, null, $childEnv);
        proc_close($proc);

        $result = json_decode(file_get_contents($outputFile) ?: '{}', true);
        @unlink($outputFile);

        $triggerError = $result['error'] ?? 'unknown';
        expect($result['ok'] ?? false)->toBeTrue("Trigger script failed: {$triggerError}");

        $expectedLapsText = number_format($lapsBefore + 1);

        // Livewire re-renders reactively on the real echo:activity,.lap.submitted broadcast —
        // no page reload. assertSee() checks once and throws immediately rather than polling
        // (confirmed against the plugin's own source), so this retries by hand — queue worker
        // poll + Reverb push + Livewire round-trip realistically takes a few seconds, not one.
        $deadline = microtime(true) + 15;
        $delivered = false;

        while (microtime(true) < $deadline) {
            try {
                $page->assertSee($expectedLapsText);
                $delivered = true;
                break;
            } catch (Throwable) {
                Sleep::usleep(500_000);
            }
        }

        if (! $delivered) {
            $debugMessage = 'no console logs captured';

            try {
                $page->assertNoConsoleLogs();
            } catch (Throwable $consoleException) {
                $debugMessage = $consoleException->getMessage();
            }

            $echoState = $page->script(<<<'JS_WRAP'
            () => {
                if (typeof window.Echo === 'undefined') { return 'window.Echo is undefined'; }
                if (typeof window.Echo.connector === 'undefined') { return 'window.Echo.connector is undefined'; }
                const connector = window.Echo.connector;
                const conn = connector.pusher?.connection;
                return JSON.stringify({
                    connectorType: connector.constructor?.name,
                    state: conn?.state,
                    socketId: conn?.socket_id,
                });
            }
            JS_WRAP);

            $rawReceived = $page->script('() => window.__echoReverbTestReceived');

            file_put_contents(sys_get_temp_dir().'/echo_reverb_debug.txt', $debugMessage."\n\nEcho state: {$echoState}\n\nRaw listener received: ".json_encode($rawReceived)."\n\nPage content:\n".$page->content());
        }

        expect($delivered)->toBeTrue("Expected Quick Stats to show \"{$expectedLapsText}\" via a real Echo/Reverb push within 15s, but it never appeared.");
    } finally {
        if ($result !== null && ($result['ok'] ?? false)) {
            $db = productionMysqlDirect();
            $mapId = $db->table('maps')->where('name', $result['mapName'])->value('id');
            $serverId = $db->table('servers')->where('ip', $result['ip'])->where('port', $result['port'])->value('id');
            $playerId = $db->table('players')->where('hash', $result['playerHash'])->value('id');

            // Delete children/pivots before parents — `servers_maps`/`players_servers` both hold
            // FK references that block a naive parent-first delete (hit and fixed manually
            // while validating this test, see docs/testing.md).
            if ($mapId !== null) {
                $db->table('lap_times')->where('map_id', $mapId)->delete();
                $db->table('servers_maps')->where('map_id', $mapId)->delete();
            }
            if ($serverId !== null) {
                $db->table('players_servers')->where('server_id', $serverId)->delete();
            }

            $db->table('maps')->where('id', $mapId)->delete();
            $db->table('players')->where('id', $playerId)->delete();
            $db->table('servers')->where('id', $serverId)->delete();
        }

        config(['database.default' => $originalDefaultConnection]);
        config(['database.connections.mysql' => $originalMysqlConfig]);
        config(['cache.default' => $originalCacheDefault]);
        DB::purge('mysql');
    }
})->skip(
    fn () => env('ALLOW_LIVE_BROADCAST_TEST') !== true,
    'Touches the real live production database and briefly broadcasts to real visitors — opt in explicitly with ALLOW_LIVE_BROADCAST_TEST=true. See docs/testing.md\'s "Real Echo/Reverb browser delivery" section.',
);
