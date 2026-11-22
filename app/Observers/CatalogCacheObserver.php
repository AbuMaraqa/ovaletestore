<?php

namespace App\Observers;

use App\Support\Seo\SitemapBuilder;

/** Any catalog change invalidates cached sitemaps (regenerated lazily on next request). */
class CatalogCacheObserver
{
    public function saved(): void
    {
        SitemapBuilder::flush();
    }

    public function deleted(): void
    {
        SitemapBuilder::flush();
    }
}
