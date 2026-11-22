<?php

namespace App\Http\Controllers;

use App\Settings\SeoSettings;
use App\Support\Seo\SitemapBuilder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Sitemap index → one sitemap per locale × type (pages, categories, products) with hreflang alternates.
 * Cached for an hour and flushed when catalog content changes (see SitemapBuilder::flush()).
 */
class SitemapController extends Controller
{
    public function index(SitemapBuilder $builder): Response
    {
        $xml = Cache::remember('sitemap:index', 3600, fn () => $builder->index()->render());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function show(SitemapBuilder $builder, string $locale, string $type): Response
    {
        $xml = Cache::remember("sitemap:{$locale}:{$type}", 3600, fn () => $builder->build($locale, $type)->render());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
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
