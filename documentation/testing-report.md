# Testing Report

Last run: 2026-11-23 — `php artisan test` → **66 passed, 336 assertions** (Pest 5, SQLite in-memory). `vendor/bin/pint` → clean. `vendor/bin/phpstan analyse` (Larastan, level 5) → **no errors** (159 baseline entries, down from 165). `npx vue-tsc --noEmit` → clean. `npm run build` → OK.
`vendor/bin/pint` â†’ clean. `vendor/bin/phpstan analyse` (Larastan, level 5) â†’ **no new errors**; 164 pre-existing findings (down from 255 after annotating translated attributes) recorded in `phpstan-baseline.neon` (mostly Astrotomic magic attributes and untyped Eloquent generics â€” to be reduced by adding `@property` annotations). `npx vue-tsc --noEmit` â†’ clean. `npm run build` â†’ OK.

| Suite | Covers |
|---|---|
| Catalog/ProductVariationsTest | simple product default variant; combination generation; variable product with per-variant price/sale/stock; relational storage (pivot rows, no JSON); invalid/foreign terms rejected; duplicate combinations rejected; stable variant ids on re-save + removal of dropped combinations |
| Storefront/CartAndCheckoutTest | guest cart add/merge quantity; live price recalculation; out-of-stock and stock-limit refusal; cart-item IDOR protection; order placement with price/name/SKU snapshots, stock decrement, cart emptied; checkout validation; status-transition rules; cancellation restock + note |
| Admin/AuthorizationTest | guest redirect; non-staff 403; deactivated staff 403; role matrix (product vs order manager); super-admin cannot be granted; admin cannot edit super-admin; admin product create with validation + XSS sanitizing + price conversion |
| Localization/LocalizedRoutesTest | ar/en/he pages with correct `lang`/`dir`; legacy Arabic URL with diacritics; heâ†’enâ†’ar fallback; cross-locale slug 301; server-rendered canonical/hreflang/JSON-LD; sitemap content excludes private pages |
| Security/SecurityTest | security headers; no-store on admin; disguised PHP upload rejected; safe generated filenames; login throttling (429); admin routes not leaked to storefront |
| Migration/WooImporterTest | idempotent import (re-run updates, no duplicates); bilingual name split; Arabic slug preserved; HTML sanitized; duplicate SKU handling; TranslatePress 301; dry-run writes nothing; `-scaled` original preference |
| Storefront/HomePageTest | all homepage sections present; best-sellers label only when backed by real orders (ranked by quantity); on-sale catalog filter |
| Storefront/CartAndCheckoutTest (shipping) | chosen method charged and snapshotted; free-over threshold; pickup without address; inactive / below-minimum methods rejected; admin CRUD + permission; checkout and admin pages render |
| Unit/BilingualHtmlTest | bilingual description split (block and line level), single-language passthrough, label removal, trailing-English product names |
| Unit/BilingualNameTest | name-splitting edge cases (`Mr. Chef`, missing English, HTML entities, trailing Latin category names) |

## Manual / HTTP verification (local `php artisan serve`)
All storefront pages (ar/en/he home, shop, search, category, product, cart, login, sitemaps, robots) return 200 in ~50â€“110 ms with the full 859-product catalog; legacy `/en/product/{arabic-slug}` returns 301 to the new English URL.

## Not yet tested
Visual/responsive checks in a real browser (no browser automation was available in this session), Lighthouse scores, MySQL-specific behaviour (tests run on SQLite).
