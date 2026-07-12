<?php

use App\Models\LapTime;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

// Real browser coverage (Pest's browser plugin, Playwright/Chromium under the hood) for the
// podium/lap-detail modal: click-driven open/close, keyboard-driven open (podium cards are real
// `<button>`s), and Escape-to-close, all console-error-free at desktop and mobile widths.
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

it('opens the podium card via keyboard (Enter) and closes the modal with Escape', function () {
    $map = seedMapWithLap();

    $page = visit("/maps/{$map->id}");

    $page->assertNoJavascriptErrors()
        ->assertDontSee('LAP DETAIL')
        ->keys('[wire\\:click="openLap(0)"]', ['Enter'])
        ->assertSee('LAP DETAIL')
        ->keys('[aria-label="Close"]', ['Escape'])
        ->assertDontSee('LAP DETAIL')
        ->assertNoJavascriptErrors();
});
