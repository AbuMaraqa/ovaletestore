<?php

namespace App\Observers;

use App\Queries\PosCatalog;
use App\Support\Seo\SitemapBuilder;

/** Any catalog change invalidates cached sitemaps and the POS catalog (rebuilt lazily on next request). */
class CatalogCacheObserver
{
    public function saved(): void
    {
        SitemapBuilder::flush();
        PosCatalog::forget();
    }

    public function deleted(): void
    {
        SitemapBuilder::flush();
        PosCatalog::forget();
    }
}
