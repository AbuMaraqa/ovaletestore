# Admin operations: permissions, activity log, tables, customers, traders, currencies, email

Written 2026-11-24. This covers the admin features added in that milestone: what each one does, where it lives in the code, and the rules it enforces.

## Permissions (one per button)
- **Registry:** `app/Support/Permissions.php`. Every action has its own permission, for example `products.update`, `orders.update_status`, `traders.approve` and `settings.mail`. The list is grouped so the roles screen can show it by section. Labels live in `lang/*/permissions.php`.
- **Sync:** `php artisan permissions:sync` creates permissions that are new in the registry, grants them to the built-in roles, and removes retired ones. **Run it on every deploy, right after `migrate`.**
  - It wraps `RolesAndPermissionsSeeder`.
  - Roles edited in the admin keep their edits.
  - Removing a permission from the registry also strips it from every role that had it.
- **Roles screen:** Admin → Staff → Roles (`RoleController`).
  - `super-admin` cannot be edited.
  - Built-in roles cannot be renamed or deleted.
  - A role that still has users cannot be deleted.
- **No privilege escalation:**
  - Nobody can grant or revoke a permission they do not hold (`RoleController::validated`).
  - Nobody can assign a role, or manage an account, with more power than their own (`UserController::assignableRoles` and `UserPolicy::outranks`).
- **Enforcement:** every controller action is authorized on the server. The Vue side only hides buttons (`useCan()`).
- **Test:** `tests/Feature/Admin/PermissionsAndRolesTest.php` fails if code checks a permission that is missing from the registry.

## Activity log (spatie/laravel-activitylog v5)
- **What gets recorded:**
  - Models record changes through `App\Models\Concerns\RecordsActivity`: created, updated and deleted, with old and new values in `attribute_changes`, plus the user who did it.
  - Every entry also stores the IP and browser, added in `AppServiceProvider::configureActivityLog`.
- **Child rows:** variants, translations, notes, payments and trader prices store their parent in the indexed `parent_type`/`parent_id` columns. A product's history therefore also shows its price, stock and translation changes.
- **Recorded by hand:**
  - settings changes, with secrets masked (`ActivityLog::settingsChanged`)
  - role and permission changes
  - user role changes
  - product gallery changes
  - sign-ins, sign-outs, failed sign-ins and password resets (`RecordAuthActivity`)
- **Never logged:** passwords, tokens, 2FA secrets. A failed sign-in keeps the typed value only if it is a valid email address; anything else is stored as `(invalid)`.
- **Who can see what:** `activity.view` opens only the channels the viewer may already see (`ActivityLog::visibleLogs`). For example, someone with only `products.view` sees the catalog log but not orders or users.
- **Where to see it:**
  - Admin → Activity log, with filters for channel, event, record type, user and date.
  - The "⋯ → Activity log" action on any table row, which opens a side panel.
- **Bulk imports don't log:** `woo:migrate`, `CatalogSeeder`, `OrderSeeder` and `CustomerSeeder`.
- **Retention:** `activitylog:clean` is scheduled daily and deletes entries older than 365 days.

## List screens (server-defined tables + DataTable)
- **Server:** `App\Support\DataTable` defines one URL contract shared by every screen:
  - `filter[search]` and `filter[<name>]`
  - `filter[from]` / `filter[until]` for date ranges
  - `sort=-column`
  - `per_page` (10, 25, 50 or 100)
  
  spatie/laravel-query-builder whitelists the allowed filters and sorts per screen.
- **Client:** `Components/DataTable/*` provides debounced search, a filter panel with removable chips, sortable headers, a column chooser (remembered per browser), pagination, row selection and row actions.
- **Server-defined tables (`app/Tables`)** — every admin list is one class (the project's own "Inertia Table"):
  - `App\Tables\Table` subclasses in `app/Tables/Admin` declare `query`, `columns` (`Column::text|badge|boolean|money|number|date` with `link`, `sub`, `avatar`, `image`, `chips`…), `filters` (`Filter::select`, `Filter::dateRange`), `search`, `defaultSort`, row `actions` (incl. `extra` buttons) and `bulkActions`.
  - Controller: `Inertia::render('Admin/X/Index', (new XTable($request))->props('rows'))` → the page gets `rows`, `state` and `table` and renders `<ResourceTable :table :page="rows" :state />`. A page may override a cell with `#cell-<key>` or add row buttons with `#row-actions`.
  - **Bulk on every table:** `POST admin/tables/{key}/bulk {action, ids}` (max 500). `BulkAction::make|toggle|download`; each action has its own permission, and runs row by row with the same rules as the single-row action (policies, "not the default currency", "not yourself"…). Rows it may not touch are skipped and listed in the flash message.
  - **Export on every table:** `GET admin/tables/{key}/export` streams a UTF-8 CSV (BOM, formula-safe) of the filtered rows, or only the selection (`filter[ids]=1,2`). Same permission as the list.
  - Register a new table in `App\Tables\Tables::ALL`. Tests: `tests/Feature/Admin/TablesTest.php`.
- **Row actions:** the main action (edit, else view) is shown inline. Activity log and delete sit in an "⋯" menu, and delete asks for confirmation. The server sends an action's URL only when the user holds that permission.
- **Dialogs** (`ConfirmHost`, `KtModal`, the activity side panel and the popovers) trap focus, close on Esc, and return focus to the button that opened them.

## Customers
- **Admin → Customers:** accounts with the `customer` role only, plus their orders and total spend. Staff accounts can never be opened or edited from this screen.
- **WooCommerce migration:**
  1. `php artisan woo:export-customers`
  2. `db:seed --class=CustomerSeeder`
  3. `db:seed --class=OrderSeeder`
  4. `woo:verify-orders`
- **Old accounts:** they get a random password, and customers set their own through "forgot password".
- **Matching by email:** an existing account is linked only if it is a verified, customer-only account.
- **Order ownership:** orders are linked to an account only if they were placed while signed in (WooCommerce `customer_id`). Guest orders stay guest orders.

## Traders (wholesale)
- **Price tiers:** Admin → Traders → Price tiers (`price_tiers`). Each tier has an automatic % off the regular price, and the product form takes an optional exact price per variant (`price_tier_prices`).
- **What a trader pays:** `App\Support\PriceContext` resolves the price once per request through `ProductVariant::effectivePrice()`: the exact tier price, else the regular price minus the tier discount, and never more than the current retail price.
- **Who gets trader prices:** only approved, active traders on an active tier. Guests, customers, staff and queue jobs pay retail.
- **Account flow:**
  1. The customer asks for trader prices, either at sign-up ("أنا تاجر") or from their account page.
  2. Everyone with `traders.approve` gets a notification in the admin bell, and a sidebar badge shows the count.
  3. A reviewer approves the request on a tier, or rejects it.
  4. On first approval the trader receives an email.
- **Orders** store `price_tier_id`, the tier they were priced with.
- **Old store:** the 3 WooCommerce `wholesale_customer` accounts were imported as approved traders on the "جملة" tier.
  - The old Wholesale Prices plugin had **no** prices filled in: `woo:export-wholesale-prices` found 0.
  - `WholesalePriceSeeder` is there in case prices are added and exported later.

## Site font
Admin → Settings → General → «خط الموقع». Code: `App\Support\SiteFont` and `FontSettingsFields.vue`.

- **Default:** Tajawal, bundled with the build.
- **Custom font:** for a licensed font such as OT Magnit Arabic, upload its web files.
  - The regular weight (400) is required. Medium (500) and bold (700) are optional; the browser imitates any weight you skip.
  - Accepted formats are WOFF2 (best), WOFF, TTF and OTF, up to 4 MB each.
  - Each file is checked by its magic bytes, then stored under `storage/fonts/` with a generated name.
- **Where it applies:** choose store only, or store + admin.
- **How it is served:** `app.blade.php` adds `@font-face` and a `--font-sans` override in a nonce'd `<style>`, preloads the regular weight, and keeps Tajawal as the fallback.
- **Preview:** the settings page previews the font before saving, using the FontFace API (CSP `font-src blob:`).

## Currencies
Admin → Currencies, built on `App\Models\Currency` and `App\Support\CurrencyContext`.

- **Base currency:** one row is the default, or base, currency (ILS today). Every stored amount is in it: product and trader prices, shipping, the minimum order and company payments. Its rate is always 1.
- **Adding a currency:**
  - The owner types the rate the way they think about it: "1 USD = 3.70 ₪". The system stores the inverse, which is USD per 1 ILS.
  - A new currency is saved **inactive**. Turn it on once the rate is checked.
- **Storefront:**
  - The visitor picks a currency from the top bar or the mobile menu, and the choice is kept in the session (`ApplyStorefrontCurrency`).
  - All prices are shown converted per unit.
  - The checkout says which currency will be collected on delivery.
  - Rules (minimum order, free shipping, shipping availability) are always checked in the base currency.
- **Orders:**
  - An order is charged in the visitor's currency.
  - It stores `currency`, `exchange_rate` and `base_total`, which is the exact base amount.
  - Reports, the dashboard, customer and trader spend, and company balances all use `base_total`.
  - If the currency or its rate changed after the checkout page was shown, the order is refused (`PlaceOrder::assertCurrencyUnchanged`). The customer sees the message and the new total.
- **POS:** sells in the base currency only.
- **Changing the default currency** (`currencies.set_default`, `SetBaseCurrency`):
  - It converts every stored base amount at the new currency's rate in one transaction, and re-expresses past orders' `exchange_rate` and `base_total`.
  - It runs at most once per request, even on a double click.
  - Converted prices will not be round numbers. Take a backup first.

## Email (order notifications)
- **Settings:** Admin → Settings → Email (`MailSettings`) holds the sender name and address, up to 10 store inboxes that receive new orders, and three switches:
  - new order → store
  - confirmation → customer
  - status change → customer
- **Mail server:** the SMTP details stay in `.env` (`MAIL_*`). The settings page shows whether mail is actually being delivered and has a "send test email" button, throttled to 5 per minute.
- **Templates:** `resources/views/mail/order.blade.php` is a WooCommerce-style template rendered in the customer's language and text direction.
  - Links include the locale prefix even when built in a queue worker.
  - The status email names the status from the moment it changed.
- **Sending:** `SendOrderEmails` and the mailables are queued (after commit), so a mail problem can never break checkout. Run a queue worker in production.

## Order status rules
- An order can move forward to any later step: pending → confirmed → processing → shipped → completed.
- It can be cancelled until it has shipped. Cancelling returns its items to stock.
- It can never move backwards.
- Deleting an order (`orders.delete`) re-reads it under a row lock and returns stock if the goods never left (`DeleteOrder`).

## Printing (laravel-mpdf)
Every printed document is a PDF built by `App\Support\Pdf\OrderDocuments`. They share one letterhead (`resources/views/pdf/layout.blade.php`), and the details come from Settings → Invoices.

- **Invoice** (`pdf/invoice`):
  - A4, in the order's language.
  - Issued automatically when an order is confirmed, or at whatever later status it jumps to first. Code: `IssueInvoiceOnConfirmation` and `IssueInvoice`.
  - Numbers run per year: INV-YYYY-00001.
  - Staff open it from the order page; customers download it from their account.
  - The email of the status change that issued it carries the PDF.
  - Older confirmed orders get invoices with `php artisan invoices:backfill`.
- **Sales order** (`OrderDocuments::salesOrder`):
  - For company orders, with signature and stamp boxes.
  - The rep prints it from the POS receipt (`pos.orders.pdf`); staff print it from the order page.
- **Account statement** (`pdf/statement`): a company's on-account orders and payments with the running balance (`App\Queries\CompanyStatement`). Printed from the company page.
- **Shipping label** (`pdf/shipping-label`): 10×15 cm thermal label with recipient, phone, amount to collect, and a Code128 barcode of the order number.
- **Fonts:**
  - XB Riyaz is used for Arabic and DejaVu for numbers and ₪.
  - When the site font (Settings → General) is a TTF/OTF and applies to the language, the documents use it.

## Email
- **Order emails** (`mail/order.blade.php`): preheader, status tracker, product photos, totals, and WhatsApp/phone help. They are email-client safe: tables and inline styles only.
- **SMTP server:** Settings → Email (`App\Support\MailServer`).
  - The password is stored encrypted and never sent back to the browser.
  - Changing the server restarts the queue workers.

## POS companies
Sales reps can add a company and correct its contact details from the POS (`pos.companies.create` / `pos.companies.update`). Notes, the active flag, balances and payments stay admin-only. The same rules apply in both places (`CompanyRequest`).

## Site icons
`php artisan branding:icons` (also runs after a logo or favicon upload) generates `favicon.ico`, the PNG favicons, home-screen icons and `/site.webmanifest`. Small sizes use a brand monogram; large sizes use the trimmed logo.

## SEO & site traffic
- Settings → SEO: home title/description, default description, share image, indexing switch, X account, Google/Bing verification codes, GA4 Measurement ID, links to the sitemap and robots.txt.
- Product / category / brand edit screens: "Search engines" card (robots, canonical, share image, Google preview per language).
- Site traffic page + dashboard card (GA4 via spatie/laravel-analytics), permission `analytics.view` (admin, content-manager). Details: documentation/seo-implementation.md.
