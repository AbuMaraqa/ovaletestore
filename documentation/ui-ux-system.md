# UI / UX System

## Two surfaces, one token base
- **Storefront** (`resources/css/storefront.css`): premium, warm "pâtisserie" direction — cream surfaces (`--color-cream`), cocoa ink (`--color-cocoa`), caramel brand scale (`--color-brand-*`, OKLCH). Rounded 1.25–2 rem cards, soft layered shadows, generous whitespace. No admin CSS is shipped to shoppers.
- **Admin** (`resources/css/admin.css`): Metronic 9.5 design system (KTUI component CSS + demo1 sidebar/header layout + Keenicons), primary color mapped to the brand caramel; light/dark mode.

Shared: `theme.css` — fonts + brand tokens.

## Typography
**Tajawal** (Arabic + Latin, weights 400/500/700/800) is the brand typeface for storefront and admin; Hebrew glyphs fall back to IBM Plex Sans Hebrew. Self-hosted via Fontsource (CSP-friendly, unicode-range subsetting). Base size 17px, line-height 1.75 for ar/he.

## Motion (GSAP + Lenis)
- Lenis smooth scrolling on the storefront, driven by the GSAP ticker so ScrollTrigger stays in sync; paused while drawers are open (`lockScroll`); inner scroll areas opt out with `data-lenis-prevent`.
- Homepage: hero intro timeline (word-by-word title, image scale-in) + scrubbed parallax; USP stagger; infinite GSAP brand marquee (pauses on hover/focus, follows reading direction); batched `[data-reveal]` reveals; layered parallax collage in the coffee spotlight; clip-path reveal on the offers banner.
- Admin: page-enter fade on navigation, KPI count-up, animated bars, self-drawing SVG revenue line.
- `prefers-reduced-motion`: Lenis is not started and no GSAP context is created — content renders static.

## Homepage sections (same order as the original store)
Hero (with search) → 4 USPs (global products, quality, exclusive offers, secure payment) → brand marquee → category rail with photos → featured products → coffee spotlight → best sellers (titled "Picked for you" until real sales data exists) → exclusive offers / latest deals (links to `/shop?sale=1`).

## RTL / LTR
`<html dir>` from mcamara per locale. All layout uses logical utilities (`ms/me/ps/pe/start/end`, `rtl:` variants for directional icons and drawer slide direction). Prices, SKUs, phones are wrapped in `dir="ltr"` to avoid bidi scrambling.

## Storefront components (`Components/Storefront`)
SiteHeader (announcement bar, sticky blurred header, mega-menu categories, search, language switcher, cart badge), MobileMenu (inline-start drawer, focus/escape handling), ProductCard (contained square image frame to normalise inconsistent source photos, sale/out-of-stock badges, quick-add for simple products), ProductGallery (LCP image eager/high priority), VariantPicker (button/select/color, unavailable combos disabled), QuantityInput, OrderSummary, Pagination, WhatsAppButton.

## Admin (faithful Metronic demo1 port)
Sidebar with logo, persisted collapse toggle, headings, accordion groups (Products, Settings) with bullets and `.show/.here/.active` states; header with breadcrumb, search modal (pages + product/order search), pending-orders notification badge, dark mode, user dropdown (`kt-dropdown-menu` markup: store link, languages, logout); Metronic toolbar and footer. Dashboard: KPIs vs last month, 30-day revenue chart, catalog data-quality panel, recent orders, largest categories.

## Admin component layer (Metronic → Vue)
`Components/UI`: KtButton, KtCard, KtBadge, KtModal, KtPagination, KtIcon, PageHeader. `Components/Forms`: FormField (label/help/error + aria wiring), LocaleTabs (ar/en/he with error dots). KTUI **JavaScript is not loaded** — open/close, tabs, drawers and dropdowns are Vue state toggling the KTUI classes (`.open`, `.active`), which avoids DOM conflicts with Vue.

## Accessibility baseline
Skip link, visible focus rings, 44 px touch targets, labelled icon buttons, `aria-live` price + toasts, `aria-pressed` on option buttons, form errors linked via `aria-describedby`, `prefers-reduced-motion` respected.

## Images
`<ResponsiveImage>` → `<picture>` AVIF + WebP srcsets (400/800/1600 w) with original fallback while conversions are pending; lazy by default, `priority` for above-the-fold.

## Next design steps
Real logo upload (Settings → General), hero photography/banners, brand strip once brands are curated, a WYSIWYG for descriptions, browser-based visual QA on mobile/tablet/desktop in all three languages.
