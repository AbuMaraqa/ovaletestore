# Testing Report

Last run: 2026-11-24 — `php artisan test` → **115 passed, 662 assertions** (Pest, SQLite in-memory). `vendor/bin/pint` → clean. `vendor/bin/phpstan analyse` (Larastan) → **no errors** (107 baseline entries, down from 110). `npx vue-tsc --noEmit` → clean. `npm run build` → OK. `composer audit` / `npm audit` → 0 advisories.

| Suite | Covers |
|---|---|
| Catalog/ProductVariationsTest | simple product default variant; combination generation; variable product with per-variant price/sale/stock; relational storage (pivot rows, no JSON); invalid/foreign terms rejected; duplicate combinations rejected; stable variant ids on re-save + removal of dropped combinations |
| Storefront/CartAndCheckoutTest | guest cart add/merge quantity; live price recalculation; out-of-stock and stock-limit refusal; cart-item IDOR protection; order placement with price/name/SKU snapshots, stock decrement, cart emptied; checkout validation; status-transition rules; cancellation restock + note |
| Admin/AuthorizationTest | guest redirect; non-staff 403; deactivated staff signed out; role matrix (product vs order manager); super-admin cannot be granted; admin cannot edit super-admin; admin product create with validation + XSS sanitizing + price conversion |
| Localization/LocalizedRoutesTest | ar/en/he pages with correct `lang`/`dir`; legacy Arabic URL with diacritics; heâ†’enâ†’ar fallback; cross-locale slug 301; server-rendered canonical/hreflang/JSON-LD; sitemap content excludes private pages |
| Security/SecurityTest | security headers; no-store on admin and POS; deactivated users signed out; e-mail change needs current password; image downloader host allow-list (SSRF); disguised PHP upload rejected; safe generated filenames; login throttling (429); admin routes not leaked to storefront |
| Migration/OrderImportTest | Woo order → agorot/status/address/region/locale/notes mapping (refund note, POS channel); seeder idempotent, links products + customers + delivery region, keeps stock untouched; `woo:verify-orders` passes/fails; export uses wp-admin cookie login + nonce and GET only; failed login exports nothing |
| Migration/WooImporterTest | idempotent import (re-run updates, no duplicates); bilingual name split; Arabic slug preserved; HTML sanitized; duplicate SKU handling; TranslatePress 301; dry-run writes nothing; `-scaled` original preference |
| Storefront/HomePageTest | all homepage sections present; best-sellers label only when backed by real orders (ranked by quantity); on-sale catalog filter |
| Storefront/CartAndCheckoutTest (shipping) | chosen method charged and snapshotted; free-over threshold; pickup without address; inactive / below-minimum methods rejected; admin CRUD + permission; checkout and admin pages render |
| Operations/OperationsTest | order status over HTTP (permissions, invalid transition, note); cancel restores a sold-out variant to *in stock* + flushes the POS cache; double cancel never restocks twice; admin order list/show; cart update/remove (qty 0 removes); deleted product dropped from cart (no 500); guest→customer cart merge; minimum order + "missing amount"; guest checkout disabled; honeypot omitted/filled rejected (checkout + register); registration throttle 429; signed/expired thank-you URL; free-delivery progress; deactivated login refused; password change needs current password; admin self-lockout guards; super-admin role preserved; store settings + currency; company payment cross-company 404 + balance; company update/delete; inactive company hidden in POS; every public page + sitemaps 200; sale % and matching struck price on cards |
| Unit/BilingualHtmlTest | bilingual description split (block and line level), single-language passthrough, label removal, trailing-English product names |
| Unit/BilingualNameTest | name-splitting edge cases (`Mr. Chef`, missing English, HTML entities, trailing Latin category names) |

## Manual / HTTP verification (local `php artisan serve`)
All storefront pages (ar/en/he home, shop, search, category, product, cart, login, sitemaps, robots) return 200 in ~50â€“110 ms with the full 859-product catalog; legacy `/en/product/{arabic-slug}` returns 301 to the new English URL.

## Not yet tested
Visual/responsive checks in a real browser (no browser automation was available in this session), Lighthouse scores, MySQL-specific behaviour (tests run on SQLite).
