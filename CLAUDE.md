# OVALETE STORE — Development Rules

Laravel 13 + Inertia v3 + Vue 3 (TypeScript, Composition API) + Tailwind 4 + Metronic 9.5 (KTUI) admin.
Multilingual: **ar (default, RTL)**, en (LTR), he (RTL). Currency: ILS (stored in agorot).

## Commands (Windows: run php/composer/npm through PowerShell)
- `composer dev` — server + queue + vite
- `php artisan test` (Pest) · `vendor/bin/pint` · `vendor/bin/phpstan analyse`
- `npm run build` · `npx vue-tsc --noEmit` (type check)
- `php artisan woo:inspect | woo:migrate [--images] [--dry-run] | woo:verify`
- `php artisan woo:export-orders` → `db:seed --class=OrderSeeder` → `woo:verify-orders` (orders file holds customer data: git-ignored)
- `php artisan sitemap:generate`
- `php artisan woo:export-customers` → `db:seed --class=CustomerSeeder` (before OrderSeeder; personal data: git-ignored)
- Deploy: `migrate --force` → `permissions:sync` → `config:cache` + `route:trans:cache` + `view:cache` + `event:cache` (never plain `route:cache`/`optimize`: mcamara needs per-locale routes) — see documentation/deployment-guide.md; + a queue worker and cron `schedule:run`
- Admin features (permissions, activity log, DataTable, customers, traders, currencies, email): documentation/admin-operations.md

## Architecture (see documentation/architecture.md)
- `app/Actions/<Domain>` — one business operation per class, `handle()` method, transactional where needed.
- `app/Http/Controllers/{Admin,Storefront}` — thin: authorize → validate (FormRequest) → call Action → return Inertia/redirect.
- `app/Http/Resources` — the ONLY way models reach Inertia props. Never pass raw models/arrays of internal columns.
- `app/Policies` — every admin operation authorized server-side (`$this->authorize` / `can` middleware). Hiding buttons is UX, not security.
- `app/Settings` — spatie/laravel-settings. Secrets live in `.env`, never in settings.
- `app/Support` — small framework-agnostic helpers (Money, Seo, Locales).
- No empty interfaces/repositories. Use Eloquent directly inside Actions/Queries.

## Domain rules
- **The purchasable unit is `ProductVariant`.** A simple product has exactly one default variant (empty signature). Variable products have one variant per attribute-term combination. Price/SKU/stock live on variants.
- Money is integer minor units (`int` agorot). Format only at the edge (`App\Support\Money`).
- Variations are relational (`product_variants` + `product_variant_term`), never JSON.
- Order items snapshot name/SKU/variant label/unit price at checkout.

## Translations
- DB content: astrotomic/laravel-translatable (`*_translations` tables, `HasTranslations` trait in `app/Models/Concerns`). Fallback chain: he→en→ar, en→ar, ar→en.
- Slugs are per-locale on translation rows, unicode-preserving (`Str::slug($v, '-', null)`), unique per locale.
- Routing: mcamara/laravel-localization, **every** language prefixed (`/ar/...`, `/en/...`, `/he/...`). Unprefixed URLs (incl. legacy WooCommerce `/product/{slug}/`) get one 301 to `/ar/...` (`RedirectToLocalePrefix`). In tests call `$this->useRouteLocale('en')` before hitting `/en` routes (Pest beforeEach sets `ar`).
- UI strings: `lang/{ar,en,he}.json` → shared once to Vue → `useTrans()` composable. Never hardcode UI text in Vue.
- RTL: use logical utilities (`ms-/me-/ps-/pe-/start-/end-`, `rtl:` variants). Never `ml-/mr-/left-/right-` for layout.

## Frontend
- Admin = Metronic/KTUI classes (`kt-btn`, `kt-card`, `kt-input`, …) wrapped in Vue components under `resources/js/Components/UI`. Do not add another UI framework. Do not load KTUI JS that mutates the DOM under Vue — reimplement behaviour (drawer, dropdown, accordion) in Vue.
- Storefront = own Tailwind design system (`resources/css/storefront.css`), tokens shared with admin. Font: Tajawal (+ IBM Plex Sans Hebrew fallback).
- Motion: GSAP + ScrollTrigger + Lenis only via `Composables/useMotion.ts` (`useGsap(root, ...)` auto-reverts on unmount; `[data-reveal]` + `revealOnScroll`). Always respect reduced motion. No libraries that write inline `style` attributes (strict CSP) — Vue `:style` / GSAP use CSSOM and are fine.
- Homepage content (hero, brand logos, spotlight) lives in `HomeSettings`; `php artisan woo:import-home-assets` imports the source visuals as WebP.
- Keep components < ~200 lines; extract composables (`resources/js/Composables`).
- Images: always `<ResponsiveImage>` (srcset, width/height, lazy unless `priority`).

## Security
- Never disable CSRF/validation/authorization to make something work. No `$guarded = []`.
- Inertia shared props contain only: auth user (id, name, avatar, permissions), locale info, flash, cart count, translations.
- Uploads: FormRequest with `mimes`, `max`, `dimensions`; media library stores with generated names.

## Quality gates (before saying "done")
Run tests + pint + build. Report real output. Never claim a migration result without `woo:verify` output.
Keep `documentation/*.md` updated with each milestone.
