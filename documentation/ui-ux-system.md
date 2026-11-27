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

## Marketing & readability pass (2026-11-24)
Applied from the design review (contrast computed from the OKLCH tokens, WCAG AA):
- **Arabic/Hebrew letter-spacing is always 0** (`storefront.css` base layer) — any tracking breaks Arabic joining. `uppercase`/`tracking-*` removed from Arabic labels.
- **Product card**: name 15–16px / line-height 1.55, price 18–20px extra-bold (brand colour when on sale), struck price 13px stone-500 (was 2.6:1), 44px buttons, `-X%` badge (`discount_percent`), visible focus on the title link. The struck price now belongs to the variant whose price is shown.
- **Product page**: `ProductPriceBlock` (price, `-X%`, "you save", stock pill, real countdown only when a sale ends within 14 days — no fake urgency), `ProductTrust` (cash on delivery, delivery areas, "ask on WhatsApp" with product name + link prefilled), `StickyBuyBar` (mobile, appears after the buy button scrolls away; WhatsApp float moves up), loading spinner on add-to-cart.
- **Cart**: free-delivery progress bar (`free_over` of the default delivery method), "add X to reach the minimum order", a real disabled button instead of a focusable link, larger names/totals, COD reassurance.
- **Contrast fixes**: hero CTA brand-600, offers banner darker gradient + white/90 text, header search/login, footer copyright, placeholders and filter counts → stone-500/600. Hero scrim now darkens the text side in both directions; hero line-height 1.4 so hamza/ascenders are not clipped.
- Filters: 44px inputs, 20px checkboxes. `.rich-text`: 68ch measure, bolder/larger headings, brand list markers, roomier tables.

## Next design steps
Real logo upload (Settings → General), hero photography/banners, brand strip once brands are curated, a WYSIWYG for descriptions, browser-based visual QA on mobile/tablet/desktop in all three languages.

## Store locations & contact numbers (2026-11-24)
- Taken from the original ovaletestore.com footer: 2 branches (Dura — pin of the original map; Bethlehem — no pin yet, the map searches its address) and 4 labelled numbers.
- Editable in **Admin → Settings → Contact**: labelled phone numbers (number + label per locale) and branches (name, address per locale, coordinates pasted from Google Maps as `lat, lng`), add / reorder / delete. `ContactSettings::phoneLinesFor()` / `locationsFor()` localize them (he→en→ar) and build the Google Maps embed + directions URLs.
- Shown in the footer (labelled number cards, WhatsApp, branches + one map that follows the selected branch — `StoreLocations.vue`) and on the contact page (the footer skips its map there). The map is a lazy `<iframe>`; CSP allows `frame-src https://www.google.com` only.

## Categories page & card heights (2026-11-24)
- `/{locale}/categories` (`storefront.categories`): every active category with published products as a 4:3 image tile, product count, and a "N offers" badge linking to `?sale=1` (live sales only: dates respected). Arabic-aware quick filter (ignores diacritics, unifies ا/أ/إ/آ, ة/ه, ى/ي) with an empty state that offers a product search. Linked from the header mega-menu, mobile menu, home category rail and footer; in the pages sitemap (with brands).
- Product cards are identical in height everywhere: fixed rows (category 1 line — always reserved, name 2 lines, price 1 line), `h-full` in grids and rails, image 6:5 and 40px button → shorter card.
- `useTrans` replaces placeholders in one pass, longest name first (`:to` no longer breaks `:total` — "40tal" bug on the shop count).

## Digits
Prices and numbers use Western digits (0-9) in every language (owner's choice). `App\Support\Money` formats with `@numbers=latn`, and the uploaded Arabic site font (Settings → General) is declared with a `unicode-range` that leaves out 0-9, so digits are drawn by Tajawal even where the Arabic font has Arabic-Indic digit glyphs.

## Status colours
`--color-success / --color-warning / --color-info` (theme.css) give `bg-success`, `text-warning`, `border-info`… the same hues as KTUI's badges.

## Editorial "soft pâtisserie" storefront (2026-11-28)
Owner's brief: soft, professional, world-class — and **it must not look AI-generated**. Rules that follow from it:
- **No decorative tropes**: no blurred colour blobs, glass/floating badges, sparkle icons, eyebrow labels with a rule
  above every heading, gradient text, or a card + shadow on everything. Photography and type carry the page.
- **Tokens** (`resources/css/storefront.css`): `sand` / `sand-deep` surfaces, `hairline` warm borders (instead of grey
  rings and shadows), `gold` accent dots. One radius family: `2xl` cards/photos, `3xl` bands, pills for buttons.
- **Hero**: editorial split — text on the cream page, the photo running to the page edge with one rounded inner
  corner; a quiet line with real catalogue numbers (`hero_stat_brands` / `hero_stat_categories`, pluralised).
- **Sections**: plain title + underlined "view all" text link (`SectionHeading`); USPs as one line between hairlines;
  categories as photos with captions (no boxes); product cards flat (hairline, sand photo well); `ProductCard compact`
  for dense rows (the coffee spotlight shows 6). Featured grid = 4 per row (8 products → two full rows).
- **Scroll Velocity** (`Components/Storefront/ScrollVelocity.vue`, after React Bits, rebuilt in Vue + GSAP): a
  full-width sand band between hairlines; row 1 large category names, row 2 small framed names; drifts at rest,
  speeds up with scroll speed (×6 max) and reverses when scrolling up. Rows are laid out LTR on purpose (an RTL row
  anchors the track to the right edge and a short row wraps out of view). Re-measures after `document.fonts.ready`.
  Decorative (`aria-hidden`), runs only on screen, static with reduced motion.
- **Pagination** (`Components/Storefront/Pagination.vue`): previous/next with arrows and a short window
  (1 … 4 5 6 … 22); phones show "Page 5 of 22". Page URLs keep every other query parameter.
