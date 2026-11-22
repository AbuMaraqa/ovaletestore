# Architecture

## Stack
Laravel 13 · PHP 8.4 · Inertia v3 · Vue 3 + TypeScript · Tailwind 4 · Metronic 9.5 (KTUI CSS) · Vite.
DB: MySQL 8 in production; SQLite locally/tests (migrations are portable — no MySQL-only column types).
Queue/cache: `database` locally, Redis in production.

## Layers
```
HTTP (Controllers: Admin/, Storefront/)   thin – authorize, validate, delegate, respond
  ├─ Requests/      FormRequests (incl. translated field rules)
  ├─ Resources/     JsonResource → Inertia props (whitelisted fields only)
Actions/<Domain>/   business operations (CreateProduct, SyncProductVariants, AddToCart, PlaceOrder…)
Queries/            reusable read models (CatalogQuery)
Models/             Eloquent + relations + scopes; no business workflows
  └─ Concerns/      HasTranslations (Astrotomic + fallback chain), HasTranslatedSlug
Support/            Money, Seo (SeoData + JsonLd), LocaleDirection
Settings/           spatie/laravel-settings classes
Migration/Woo/      WooCommerce source client + mappers + importer (used by woo:* commands)
Jobs/               queued image import / conversions
Policies/           one per admin resource, backed by spatie/permission
```
Boundaries: Storefront controllers never call admin actions; both share Actions/Models.

## Request flow (storefront product page)
`/en/product/{slug}` → LaravelLocalization middleware sets locale → `ProductController@show` resolves translation slug (fallback to any-locale slug + 301 to canonical) → loads product with translations, variants, terms, media (eager) → `ProductResource` → Inertia page `Storefront/Products/Show` + `seo` prop (title, description, canonical, hreflang alternates, JSON-LD) rendered server-side in `app.blade.php` and kept in sync by `<SeoHead>` on client navigation.

## Database design (summary — see database-design.md)
- `categories` (self-referencing `parent_id`) + `category_translations`
- `brands` + `brand_translations`
- `attributes` (global when `product_id` null, custom otherwise) + `attribute_translations`
- `attribute_terms` + `attribute_term_translations`
- `products` + `product_translations` (name, slug, descriptions, meta)
- `product_attributes` (product ↔ attribute, `used_for_variations`) + `product_attribute_term`
- `product_variants` (sku, prices, stock, `signature` unique per product) + `product_variant_term`
- `carts`, `cart_items` (→ variant) · `orders`, `order_items` (snapshots), `order_notes`
- `redirects` (legacy URL → new URL, 301)
- package tables: media, permissions, settings, activity_log

## Key decisions
| Decision | Why |
|---|---|
| Variant is the purchasable unit, simple product = 1 default variant | One cart/order code path; adding variations later needs no data migration |
| Money as integer agorot | No float rounding errors |
| Default locale hidden in URL, `/product/{slug}` kept | Old Arabic URLs keep working with zero redirects |
| Server-rendered SEO head in Blade from the Inertia page object | Crawlers get full meta without running JS and without an SSR Node process |
| UI translations from `lang/*.json` shared once via Inertia | Single translation source for Laravel + Vue, no extra i18n package |
| Metronic CSS + Vue-implemented behaviour | KTUI JS mutates DOM and fights Vue's virtual DOM |
