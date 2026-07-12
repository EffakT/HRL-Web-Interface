<?php

use App\Models\LapTime;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

// SEO-01 audit follow-up (2026-07-10). Dynamic titles/descriptions are wired via Livewire's
// runtime ->layoutData() (see docs/decisions.md for why the static #[Layout(...)] attribute
// can't interpolate an entity name at all), so this is real coverage of that mechanism working
// end to end, not just that the attribute string is set.
uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $server = Server::factory()->create(['id' => 1, 'name' => 'Test Server Alpha']);
    $map = Map::factory()->create(['id' => 1, 'label' => 'Test Map Alpha']);
    $player = Player::factory()->create(['id' => 1, 'name' => 'Test Player Alpha']);
    LapTime::factory()->create(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $player->id]);
});

it('interpolates the real entity name into each detail page\'s title, not a generic one', function (string $uri, string $expectedTitleFragment) {
    $this->get($uri)->assertSee("<title>{$expectedTitleFragment}", false);
})->with([
    'players.show' => ['/players/1', 'Test Player Alpha'],
    'servers.show' => ['/servers/1', 'Test Server Alpha'],
    'maps.show' => ['/maps/1', 'Test Map Alpha Leaderboard'],
    'servers.maps.show' => ['/servers/1/maps/1', 'Test Map Alpha Leaderboard on Test Server Alpha'],
    'servers.players.show' => ['/servers/1/players/1', 'Test Player Alpha on Test Server Alpha'],
]);

it('renders a canonical link and OG/Twitter meta matching the page title', function () {
    $this->get('/players/1')
        ->assertSee('<link rel="canonical" href="'.url('/players/1').'">', false)
        ->assertSee('<meta property="og:title" content="Test Player Alpha | '.config('app.name').'">', false)
        ->assertSee('<meta name="twitter:title" content="Test Player Alpha | '.config('app.name').'">', false);
});

it('renders a noindex robots meta tag and disallows crawling while indexing is off', function () {
    config(['seo.allow_indexing' => false]);

    $this->get('/')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    $this->get('/robots.txt')->assertSee('Disallow: /');
});

it('omits the robots meta tag and allows crawling once indexing is turned on', function () {
    config(['seo.allow_indexing' => true]);

    $this->get('/')->assertDontSee('name="robots"', false);
    $this->get('/robots.txt')
        ->assertDontSee('Disallow: /')
        ->assertSee('Sitemap: '.url('/sitemap.xml'));
});

it('lists top-level entities in the sitemap but not nested server-scoped routes', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'application/xml');
    $response->assertSee('<loc>'.route('players.show', ['playerId' => 1]).'</loc>', false);
    $response->assertSee('<loc>'.route('servers.show', ['serverId' => 1]).'</loc>', false);
    $response->assertSee('<loc>'.route('maps.show', ['mapId' => 1]).'</loc>', false);
    $response->assertDontSee(route('servers.maps.show', ['serverId' => 1, 'mapId' => 1]), false);
    $response->assertDontSee(route('servers.players.show', ['serverId' => 1, 'playerId' => 1]), false);
});
