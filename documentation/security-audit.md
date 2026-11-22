# Security Audit — Milestone 1

Status as of 2026-11-23. Items marked ✅ are implemented **and** covered by an automated test where noted.

## Authentication (Laravel Fortify)
- ✅ Bcrypt hashing (cost 12), `Password::defaults()` → production: min 10, mixed case, numbers, HIBP `uncompromised()`.
- ✅ Login throttling: 5/min per email+IP and 20/min per IP (test: *throttles repeated login attempts*).
- ✅ No public registration; staff accounts created by admins. Deactivated users cannot log in (generic error — no enumeration).
- ✅ Password reset, 2FA (TOTP, confirm + password confirmation) available.
- ✅ Session cookies encrypted (`SESSION_ENCRYPT=true`); logout invalidates session (Fortify).
- ✅ Super-admin created by seeder with a random 16-char password printed once — no default credentials in Git.

## Authorization (spatie/permission + Policies)
- ✅ `/admin` behind `auth` + `can:access-admin` (active user with `admin.access`).
- ✅ Every admin controller action calls `authorize()` (policy or permission) — UI visibility is cosmetic only.
- ✅ Roles: super-admin (Gate::before), admin, product-manager, content-manager, order-manager.
- ✅ Privilege escalation guards: `super-admin` cannot be assigned via UI; admins cannot edit/delete super-admins (tests).
- ✅ IDOR: cart items can only be changed through the visitor's own cart (test); media deletion checks ownership.

## Input / output
- ✅ FormRequests / validated arrays everywhere; translated fields via Astrotomic `RuleFactory`.
- ✅ Mass assignment: explicit `$fillable` on every model; no `$guarded = []`.
- ✅ XSS: rich text sanitized on write with symfony/html-sanitizer whitelist before any `v-html` (test strips `<script>`).
- ✅ SQL injection: Eloquent/bindings only; LIKE terms escaped; filters/sorts whitelisted (spatie/query-builder).
- ✅ CSRF: Laravel default on all web routes (Inertia sends XSRF token).
- ✅ Honeypot (spatie/laravel-honeypot) + rate limits on checkout (5/min, 30/h per IP) and cart (60/min/session).

## File uploads
- ✅ Content-sniffed `mimetypes:` (not extension), size limits, dimension limits (test: PHP disguised as .jpg rejected).
- ✅ Generated file names — user-supplied names/paths never reach the filesystem (test: `../../evil name.png`).
- ✅ Originals stored by medialibrary; images re-encoded to WebP/AVIF (strips embedded payloads in derivatives).
- ✅ Migration downloader verifies bytes with `finfo` + `getimagesizefromstring`, 25 MB cap.

## Headers & transport
- ✅ CSP (spatie/laravel-csp, nonce-based, self-hosted assets only; Inertia/Ziggy/Vite scripts carry the nonce).
- ✅ `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `COOP`; HSTS + `upgrade-insecure-requests` + forced https URLs in production.
- ✅ `Cache-Control: no-store` on admin, cart, checkout, auth pages (test).

## Information exposure
- ✅ Inertia shared props are a whitelist (no config, no other users' data). Admin permission list only sent on admin pages.
- ✅ Ziggy route map split: storefront pages don't receive admin routes (test).
- ✅ Order confirmation page uses a **temporary signed URL** (2h) — order numbers are not enumerable.
- ✅ Branded error pages in production; `APP_DEBUG=false` required (deployment guide).

## Remaining / to do
- ⏳ Production DB user with least privilege (deployment step).
- ⏳ `composer audit` / `npm audit` in CI (both currently report 0 vulnerabilities).
- ⏳ spatie/laravel-backup schedule + off-site disk (installed, needs credentials).
- ⏳ Staff 2FA enforcement policy (available, not yet mandatory) and a profile page to enable it.
- ⏳ Activity log viewer in the admin (logging is active on products, categories, order status).
