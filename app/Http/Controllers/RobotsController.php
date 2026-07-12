<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    /**
     * GET /robots.txt — driven by config('seo.allow_indexing') (SEO-01) rather than a static
     * file, so flipping that one config value at launch is the only step needed to open this
     * redesign environment up to crawling.
     */
    public function index(): Response
    {
        $lines = config('seo.allow_indexing')
            ? ['User-agent: *', 'Disallow:', 'Sitemap: '.url('/sitemap.xml')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
    }
}
