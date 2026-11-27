<?php

namespace App\Http\Controllers;

use App\Settings\SeoSettings;
use App\Support\Seo\SitemapBuilder;
use Illuminate\Http\Response;

/**
 * Sitemap index → one sitemap per locale × type (pages, categories, brands, products) with hreflang alternates.
 * Built by App\Support\Seo\SitemapBuilder (spatie/laravel-sitemap), cached an hour, flushed on catalog changes,
 * re-warmed nightly by `sitemap:generate`. X-Robots-Tag keeps the XML files themselves out of search results.
 */
class SitemapController extends Controller
{
    public function index(SitemapBuilder $builder): Response
    {
        return $this->xml($builder->xml());
    }

    public function show(SitemapBuilder $builder, string $locale, string $type): Response
    {
        return $this->xml($builder->xml($locale, $type));
    }

    private function xml(string $xml): Response
    {
        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'X-Robots-Tag' => 'noindex']);
    }

    public function robots(SeoSettings $seo): Response
    {
        $lines = $seo->allow_indexing && app()->isProduction()
            ? [
                'User-agent: *',
                'Disallow: /admin',
                'Disallow: /*/admin',
                'Disallow: /cart',
                'Disallow: /*/cart',
                'Disallow: /checkout',
                'Disallow: /*/checkout',
                'Disallow: /login',
                'Disallow: /*/login',
                'Disallow: /*?*q=',
                '',
                'Sitemap: '.route('sitemap.index'),
            ]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
