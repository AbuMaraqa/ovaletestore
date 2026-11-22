# SEO Implementation

No ranking promises — this documents what is technically in place.

## URL strategy (SEO continuity)
- Arabic (default) has **no prefix**: `/product/{slug}`, `/product-category/{slug}` — identical to the WooCommerce URLs. `woo:verify` confirmed **0 slug mismatches across 859 products**.
- English `/en/...`, Hebrew `/he/...`. English slugs are ASCII (e.g. `/en/product/red-fondant-1-kg`).
- Old TranslatePress URLs (`/en/product/{arabic-slug}`) → **301** to the new English slug (856 product + category redirects in the `redirects` table, served by `HandleLegacyRedirects` only when a request would 404).
- A slug from another locale 301s to the canonical slug of the requested locale. `/ar/...` redirects to the prefix-less URL. WordPress trailing slashes are normalized by the web server.

## Head tags (server-rendered, no JS needed)
`app.blade.php` renders from the page's `seo` prop: `<title>`, meta description (160 chars), robots, canonical, `hreflang` for ar/en/he + `x-default` (Arabic), Open Graph (type, title, description, url, site_name, locale, image), Twitter card. `<SeoHead>` keeps them in sync on client-side navigation (same `inertia`/`head-key` ids, no duplicates).

## Structured data (spatie/schema-org)
- Home: `OnlineStore` (Organization) + `WebSite` with `SearchAction`.
- Product: `Product` with one `Offer` per active variant (price, currency, availability, SKU, url), brand, image + `BreadcrumbList`.
- Category: `BreadcrumbList`.

## Indexing rules
- Search results, sorted or filtered listings → `noindex,follow`. Cart, checkout, thank-you, admin, auth → `noindex` and excluded from the sitemap.
- Global kill switch: Settings → SEO → "allow indexing" (turns every page into `noindex,nofollow` and robots.txt into `Disallow: /`). Non-production environments always return `Disallow: /`.
- Pagination: `?page=N` kept in canonical (self-referencing), first page canonical without the parameter.

## Sitemaps (spatie/laravel-sitemap)
`/sitemap.xml` index → 9 files: `/sitemaps/{ar|en|he}-{pages|categories|products}.xml`. Each URL lists all hreflang alternates + x-default; products include `<image:image>` (1600 px WebP). Cached 1h and flushed automatically when products/categories/translations change (`CatalogCacheObserver`).

## robots.txt
Dynamic (`SitemapController@robots`): disallows admin, cart, checkout, login, `?q=` search; references the sitemap index.

## Content quality notes
- English names exist for 826/859 products (from the bilingual source names); descriptions are Arabic-only until the TranslatePress dictionary is imported. Hebrew falls back to English.
- Image `alt` = product name in the current locale.
- Next: product SEO titles/descriptions per locale (admin fields exist), a brand landing page, and internal linking from categories.
