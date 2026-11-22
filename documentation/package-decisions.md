# Package Decisions

Versions verified on Packagist on 2026-11-22 against Laravel 13 / PHP 8.4.

| Package | Version | Purpose | Alternatives considered / notes |
|---|---|---|---|
| laravel/framework | 13.x | Core | — |
| inertiajs/inertia-laravel | ^3.7 | SPA bridge | — |
| tightenco/ziggy | ^2.7 | Named routes in Vue (locale-prefixed automatically) | hardcoding URLs (rejected) |
| laravel/fortify | ^1.43 | Headless auth: login throttling, reset, 2FA | Breeze/starter kit (pulls shadcn-vue, conflicts with Metronic) |
| spatie/laravel-settings | ^3.10 | Typed site/store/SEO settings | config files (not editable by admins) |
| spatie/laravel-medialibrary | ^11.23 | Media, conversions (WebP/AVIF via GD), responsive srcset | Pro version — not licensed; custom Vue uploader instead |
| spatie/laravel-permission | ^8.5 | Roles & permissions | Bouncer |
| astrotomic/laravel-translatable | ^11.17 | DB content translations (`*_translations` tables) | spatie/laravel-translatable (JSON columns — rejected by brief) |
| mcamara/laravel-localization | ^2.4 | Locale-prefixed routing, hidden default locale | custom middleware |
| laravolt/avatar | ^8.0 | Initials avatars (Arabic-capable font configured) | ui-avatars.com (external request, privacy) |
| spatie/laravel-sitemap | ^8.2 | Sitemap index + per-type sitemaps with hreflang | — |
| spatie/schema-org | ^5.0 | Typed JSON-LD (Product, Organization, BreadcrumbList) | hand-written arrays |
| spatie/laravel-activitylog | ^5.1 | Admin audit trail | owen-it/auditing |
| spatie/laravel-csp | ^3.31 | Content-Security-Policy with nonces | bepsvpt/secure-headers (we set other headers in our own middleware) |
| spatie/laravel-honeypot | ^4.7 | Spam protection for checkout/contact | captcha (UX cost) |
| spatie/laravel-query-builder | ^7.3 | Safe whitelisted filtering/sorting for admin tables & catalog | manual request parsing |
| spatie/laravel-backup | ^10.5 | DB + media backups | host-level only |
| pestphp/pest (+laravel plugin) | ^5.3 | Tests | PHPUnit (Pest runs on it) |
| larastan/larastan | ^3.15 | Static analysis | — |
| laravel/pint | bundled | Code style | — |

## Deliberately NOT installed
- **SEO meta packages** (ralphjsmit/laravel-seo, artesaos/seotools): both render Blade tags per request and do not fit Inertia client navigation; our `SeoData` + Blade + `<SeoHead>` covers it with fewer moving parts.
- **spatie/laravel-sluggable**: its translatable slug support targets spatie/laravel-translatable, not Astrotomic. Small `HasTranslatedSlug` concern instead.
- **spatie/laravel-image-optimizer**: needs jpegoptim/pngquant/cwebp binaries (absent on the Windows dev box). Size reduction comes from WebP/AVIF conversions at tuned quality; can be added in production later.
- **spatie/laravel-data**: Resources + FormRequests are sufficient now; avoid two DTO systems.
- **dedoc/scramble**: no public API yet.
- **laravel-vue-i18n**: replaced by sharing Laravel's own `lang/*.json` once.
