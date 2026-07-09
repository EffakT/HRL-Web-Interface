<?php

use App\Models\LapTime;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

// TEST-01 audit follow-up (2026-07-09) — the first real browser coverage for this app (Pest's
// browser plugin, Playwright/Chromium under the hood). Covers what the podium/lap-detail modal
// actually does today: real click-driven open/close and a console-error-free render at both
// desktop and mobile widths. Deliberately does NOT test Escape-to-close or a focus trap — neither
// exists yet (the podium cards are plain `<div wire:click>`, not real buttons, and the modal has
// no `role="dialog"`/`aria-modal`/focus management at all) — that gap is A11Y-01, tracked
// separately in SITE_AUDIT.md, not something to fake coverage for here.
uses(LazilyRefreshDatabase::class);

function seedMapWithLap(): Map
{
    $map = Map::factory()->create(['label' => 'Test Map']);
    $server = Server::factory()->create();
    $player = Player::factory()->create(['name' => 'Browser Test Player']);

    LapTime::factory()->create([
        'map_id' => $map->id,
        'server_id' => $server->id,
        'player_id' => $player->id,
        'time' => 55.5,
    ]);

    return $map;
}

it('loads the homepage in a real browser with no console errors', function () {
    $page = visit('/');

    $page->assertNoJavascriptErrors();
});

it('opens and closes the lap detail modal via real clicks, with no console errors', function () {
    $map = seedMapWithLap();

    $page = visit("/maps/{$map->id}");

    $page->assertNoJavascriptErrors()
        ->assertDontSee('LAP DETAIL')
        ->click('[wire\\:click="openLap(0)"]')
        ->assertSee('LAP DETAIL')
        ->assertSee('Browser Test Player')
        ->click('text=CLOSE')
        ->assertDontSee('LAP DETAIL')
        ->assertNoJavascriptErrors();
});

it('renders the leaderboard modal flow at a mobile viewport with no console errors', function () {
    $map = seedMapWithLap();

    $page = visit("/maps/{$map->id}")->on()->mobile();

    $page->assertNoJavascriptErrors()
        ->click('[wire\\:click="openLap(0)"]')
        ->assertSee('LAP DETAIL')
        ->assertNoJavascriptErrors();
});
