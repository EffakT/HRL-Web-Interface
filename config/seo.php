<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Search engine indexing (SEO-01, docs/decisions.md)
    |--------------------------------------------------------------------------
    |
    | This redesign runs alongside the real production site while it's being built out, on a
    | URL that will change/retire at launch cutover. Keep this false until launch — a sitewide
    | `noindex` meta tag is rendered and `/robots.txt` disallows crawling while it's false, so
    | nothing here gets indexed under a URL that won't be the permanent one. All the real
    | metadata (titles, descriptions, canonical URLs, OG/Twitter tags, sitemap) is built and
    | correct regardless, so flipping this to true at launch is the only step needed.
    |
    */

    'allow_indexing' => (bool) env('SEO_ALLOW_INDEXING', false),

];
