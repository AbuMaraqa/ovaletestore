<?php

namespace App\Models;

use RalphJSmit\Laravel\SEO\Models\SEO as BaseSeo;

/**
 * The per-record SEO overrides of ralphjsmit/laravel-seo (one `seo` row per product, category or brand,
 * created by the HasSEO trait). Language-dependent text (meta title/description) lives on the translation
 * rows; this row holds what is the same in every language:
 *  - robots: null = site default, or e.g. "noindex,follow" to keep one record out of search results;
 *  - canonical_url: point duplicates at another page (empty = the record's own URL);
 *  - image: an optional social-share image (public-disk path) instead of the first gallery image.
 *
 * Extends the package model (config/seo.php → model) only to replace its `$guarded = []` with an explicit list.
 *
 * @property string|null $title
 * @property string|null $description
 * @property string|null $image
 * @property string|null $author
 * @property string|null $robots
 * @property string|null $canonical_url
 */
class Seo extends BaseSeo
{
    /** Allowed robots overrides (the admin picks one; anything else is rejected by validation). */
    public const ROBOTS = ['index,follow', 'noindex,follow', 'noindex,nofollow'];

    protected $guarded = ['id'];

    protected $fillable = ['robots', 'canonical_url', 'image', 'title', 'description', 'author'];
}
