<?php

namespace App\Http\Controllers;

use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Carbon\Carbon;
use Illuminate\Http\Response;
use RuntimeException;
use SimpleXMLElement;

class SitemapController extends Controller
{
    /**
     * GET /sitemap.xml — top-level public entities only (servers, maps, players index + detail
     * pages), not the nested server-scoped routes (/servers/{id}/maps/{id},
     * /servers/{id}/players/{id}) — those are variant views of the same map/player content the
     * top-level detail page already covers (SEO-01, decided with the user).
     */
    public function index(): Response
    {
        $urls = [
            ['loc' => route('home')],
            ['loc' => route('servers.index')],
            ['loc' => route('maps.index')],
            ['loc' => route('players.index')],
            ['loc' => route('opt-in')],
            ['loc' => route('contact')],
        ];

        foreach (Server::all() as $server) {
            $urls[] = ['loc' => route('servers.show', ['serverId' => $server->id]), 'lastmod' => $server->updated_at];
        }

        foreach (Map::all() as $map) {
            $urls[] = ['loc' => route('maps.show', ['mapId' => $map->id]), 'lastmod' => $map->updated_at];
        }

        foreach (Player::all() as $player) {
            $urls[] = ['loc' => route('players.show', ['playerId' => $player->id]), 'lastmod' => $player->updated_at];
        }

        return response($this->toXml($urls), 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Built directly with SimpleXMLElement rather than a Blade view — a literal `<?xml ...?>`
     * declaration inside a Blade template trips the compiler's raw-PHP-tag guard and never
     * compiles correctly (confirmed directly: `Blade::compileString()` left the `{{ }}` line
     * completely uncompiled instead of turning it into an echo statement).
     *
     * @param  list<array{loc: string, lastmod?: ?Carbon}>  $urls
     */
    private function toXml(array $urls): string
    {
        $root = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

        foreach ($urls as $entry) {
            $url = $root->addChild('url');
            $url->addChild('loc', htmlspecialchars($entry['loc'], ENT_XML1));

            if (! empty($entry['lastmod'])) {
                $url->addChild('lastmod', $entry['lastmod']->toAtomString());
            }
        }

        $xml = $root->asXML();

        throw_if($xml === false, RuntimeException::class, 'Failed to generate sitemap XML.');

        return $xml;
    }
}
