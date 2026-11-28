# SEO Implementation

No ranking promises — this documents what is technically in place.

## URL strategy (SEO continuity)
- Every language is visible in the URL: `/ar/...`, `/en/...`, `/he/...` (owner decision, 2026-11-23).
- Legacy WooCommerce URLs (`/product/{arabic-slug}/`, `/product-category/{slug}/`, `/`) receive **one 301** to the same path under `/ar` (`RedirectToLocalePrefix`, runs before routing). Arabic slugs are byte-identical to WooCommerce (`woo:verify`: 0 mismatches), so link equity passes through a single permanent redirect.
- English slugs are ASCII (e.g. `/en/product/red-fondant-1-kg`). Old TranslatePress URLs (`/en/product/{arabic-slug}`) → **301** to the new English slug (856 rows in `redirects`, served when a request would 404).
- A slug from another locale 301s to the canonical slug of the requested locale.

## Head tags — ralphjsmit/laravel-seo + Inertia v3 server head
- One builder per page: `App\Support\Seo\SeoData` (`SeoData::make($title, $description)` or `SeoData::for($product|$category|$brand)`), fluent `->forCurrentRoute()`, `->localized()`, `->page($n)`, `->noindex()`, `->breadcrumbs([...])`, `->withJsonLd(Schema…->toArray())`.
- It fills laravel-seo's `SEOData` and returns the package's rendered tags (title, description, robots, canonical, hreflang ar/en/he + `x-default`, `<link rel="sitemap">`, Open Graph incl. `og:image:width/height`, `og:locale` + `og:locale:alternate`, Twitter card (summary / summary_large_image chosen from the image ratio), JSON-LD) as the `seo` page prop: a list of HTML strings, each with a stable `data-inertia` key.
- `app.blade.php` prints them (crawlers need no JS); `createInertiaApp({ serverHead: 'seo' })` swaps exactly those tags on every client-side visit (verified: one title/canonical/robots per page, JSON-LD replaced, nothing stale).
- Site-wide rules are transformers in `App\Providers\SeoServiceProvider`: " | Site name" suffix (unless already present), 70/160-char limits, default description per language, fallback share image (Settings → SEO, measured), `og:locale` regional codes, `twitter:site`, the indexing kill-switch, Google/Bing verification meta tags.
- Records with a public page (`Product`, `Category`, `Brand`) use `App\Models\Concerns\HasSeoPage` (laravel-seo `HasSEO` + `getDynamicSEOData()` + spatie `Sitemapable`). Their `seo` row (`App\Models\Seo`, table `seo`) holds the language-independent overrides edited in the "Search engines" card on each edit screen (`PUT admin/seo/{products|categories|brands}/{id}`, same permission as editing the record): robots (`index,follow` / `noindex,follow` / `noindex,nofollow`), canonical URL, share image. Per-language meta title/description stay on the translation rows. The card shows a live Google result preview per language.

## Structured data (spatie/schema-org + laravel-seo)
- Home: `OnlineStore` (Organization) + `WebSite` with `SearchAction`. Contact: `OnlineStore`.
- Product: `Product` with one `Offer` per active variant (price, currency, availability, SKU, url), brand, image, cleaned description.
- Breadcrumbs: laravel-seo `BreadcrumbList` markup on product, category, brand, categories and brands pages.

## Indexing rules
- Search results, sorted or filtered listings → `noindex,follow`. Cart, checkout, thank-you, admin, auth → `noindex` and excluded from the sitemap.
- Global kill switch: Settings → SEO → "allow indexing" (turns every page into `noindex,nofollow` and robots.txt into `Disallow: /`). Non-production environments always return `Disallow: /`.
- Pagination: `?page=N` kept in canonical (self-referencing), first page canonical without the parameter.

## Sitemaps (spatie/laravel-sitemap)
`/sitemap.xml` index (each entry with `<lastmod>` = newest change of that content) → 12 files: `/sitemaps/{ar|en|he}-{pages|categories|brands|products}.xml`.
- Built by `App\Support\Seo\SitemapBuilder`; records are added as `Sitemapable` models (`toSitemapTag()`): every URL lists all hreflang alternates + x-default and lastmod; products add up to 10 gallery images, categories/brands their image (image sitemap).
- Records marked noindex or canonicalised elsewhere (their `seo` row) are left out; with indexing switched off the index is empty.
- Cached 1 h, flushed on any change of products, variants, categories, brands, their translations and `seo` rows (`CatalogCacheObserver`), rebuilt nightly by `php artisan sitemap:generate` (04:00; `--write=dir` also saves the XML files). Responses carry `X-Robots-Tag: noindex`.
- No XSL stylesheet: Chrome removed XSLT, search engines never needed it.

## robots.txt
Dynamic (`SitemapController@robots`): disallows admin, cart, checkout, login, `?q=` search; references the sitemap index.

## Content quality notes
- English names exist for 826/859 products (from the bilingual source names); descriptions are Arabic-only until the TranslatePress dictionary is imported. Hebrew falls back to English.
- Image `alt` = product name in the current locale.
- Next: product SEO titles/descriptions per locale (admin fields exist), a brand landing page, and internal linking from categories.

## Analytics (spatie/laravel-analytics, GA4)
- Tracking: Settings → SEO → Measurement ID (`G-…`). `app.blade.php` prints gtag.js on storefront pages only (never admin/POS/auth) when `config('analytics.tracking')` is on (default: production only, `ANALYTICS_TRACKING`); the CSP allows Google's hosts only then. `resources/js/analytics.ts` sends one `page_view` per Inertia visit (de-duplicated by URL).
- Reports: Admin → Site traffic (`admin.analytics.index`, permission `analytics.view`) and a dashboard card. `App\Support\Analytics\SiteAnalytics` uses every package report (totals by date, visitors & views per page and per day, most visited pages, referrers, new vs returning, browsers, countries, OS) + `get()` for KPIs (users, views, sessions, engagement, avg. duration, bounce), channels and devices + `getRealtime()` (visitors in the last 30 min, own 60 s cache; the window is anchored on a full hour because the package's `Period::toMinuteRange()` derives "minutes ago" from the minute-of-hour).
- Setup (server): `ANALYTICS_PROPERTY_ID` (numeric) in `.env`; service-account JSON at `storage/app/analytics/service-account-credentials.json` (or `ANALYTICS_CREDENTIALS_PATH`), the service account added as Viewer on the GA4 property. Until then the page shows the setup guide; API errors are shown as a short message and reported to the log. Responses are cached `ANALYTICS_CACHE_MINUTES` (60).

## Site name, favicon & sitelinks in Google (2026-11-28)
- **Site name** (the bold name above a result): the home page of every language prints `WebSite` JSON-LD with
  `name` (store name in that language) and `alternateName` (its names in the other languages) — `JsonLd::website()`.
- **Favicon in results**: `<link rel="icon">` now also lists the 192×192 PNG (Google wants a square icon, a multiple
  of 48px); the 48px `.ico` stays for browsers.
- **Sitelinks** (the extra rows under the result): chosen by Google automatically — there is no tag that turns them
  on. What we control is done: a clear header menu with plain labels, unique titles/descriptions per page,
  breadcrumbs, internal links and the sitemap index. After launch: verify the domain in Search Console (code in
  Settings → SEO), submit `/sitemap.xml`; sitelinks typically appear once the site is indexed and people search the
  store by name. (Google retired the sitelinks *search box* in 2024; the `SearchAction` is kept for other engines.)
- **Contact page URL** (2026-11-28): `/{locale}/contact-us` (route `storefront.contact`, label «تواصل معنا»). The old
  `/{locale}/contact` answers with one 301 to it (`storefront.contact.legacy`), so existing links and indexed results
  keep working.
- **robots.txt** (2026-11-28): served only by `SitemapController@robots` (private areas disallowed + `Sitemap:` line;
  `Disallow: /` when indexing is off or outside production). Laravel's default `public/robots.txt` was deleted:
  nginx serves a real file before PHP, so it silently replaced these rules on the live server. When deploying by
  copying files, **delete `public/robots.txt` on the server too**. A test fails if the file comes back.
- **Search Console "Couldn't fetch" on the child sitemaps** right after submitting `/sitemap.xml` is Google's
  pending state (no "Last read" date yet): the files answer 200 with valid XML. It clears once Google crawls them
  (hours to a few days); re-submit the index only if it persists.

## Internal visit counter + Google Analytics side by side (2026-11-28)
Admin → Site traffic has a source switch (`?source=internal|google`, default internal):
- **Store counter** — `shetabit/visitor` (most-used Laravel visit logger, Laravel 13). `App\Http\Middleware\TrackStoreVisit`
  on the storefront route group logs one row per real page view (full load or Inertia visit; GET 200 only; no bots via
  the package's crawler detection, no prefetch/partial reloads, no redirects/JSON helpers, no staff). Written after the
  response (`defer`). `App\Models\StoreVisit` (config `visitor.model`) **scrubs headers (session cookies!), request
  input, IP and user agent** — the package stores all of them by default — and keeps report columns: path, slug,
  daily-rotating visitor hash, session hash, device type, channel (entrances only), referrer host, locale.
  `App\Support\Analytics\StoreVisits` returns the same report shape as `SiteAnalytics` (no countries, no new/returning).
  Rows older than 400 days: `model:prune` daily 03:45. The dashboard traffic card uses this counter.
- **Google Analytics** — unchanged (`SiteAnalytics`); shows "not connected yet" until the Data API key is installed.
- Deploy: `php artisan migrate` (visits table + report columns).
