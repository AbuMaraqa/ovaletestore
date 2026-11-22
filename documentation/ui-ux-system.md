# UI / UX System

## Two surfaces, one token base
- **Storefront** (`resources/css/storefront.css`): premium, warm "pâtisserie" direction — cream surfaces (`--color-cream`), cocoa ink (`--color-cocoa`), caramel brand scale (`--color-brand-*`, OKLCH). Rounded 1.25–2 rem cards, soft layered shadows, generous whitespace. No admin CSS is shipped to shoppers.
- **Admin** (`resources/css/admin.css`): Metronic 9.5 design system (KTUI component CSS + demo1 sidebar/header layout + Keenicons), primary color mapped to the brand caramel; light/dark mode.

Shared: `theme.css` — fonts + brand tokens.

## Typography
Self-hosted (Fontsource, no third-party requests, CSP-friendly): Inter Variable (Latin), IBM Plex Sans Arabic, IBM Plex Sans Hebrew, combined in one stack so each glyph uses the right family; browsers download only the scripts rendered (unicode-range). Arabic/Hebrew get line-height 1.7.

## RTL / LTR
`<html dir>` from mcamara per locale. All layout uses logical utilities (`ms/me/ps/pe/start/end`, `rtl:` variants for directional icons and drawer slide direction). Prices, SKUs, phones are wrapped in `dir="ltr"` to avoid bidi scrambling.

## Storefront components (`Components/Storefront`)
SiteHeader (announcement bar, sticky blurred header, mega-menu categories, search, language switcher, cart badge), MobileMenu (inline-start drawer, focus/escape handling), ProductCard (contained square image frame to normalise inconsistent source photos, sale/out-of-stock badges, quick-add for simple products), ProductGallery (LCP image eager/high priority), VariantPicker (button/select/color, unavailable combos disabled), QuantityInput, OrderSummary, Pagination, WhatsAppButton.

## Admin component layer (Metronic → Vue)
`Components/UI`: KtButton, KtCard, KtBadge, KtModal, KtPagination, KtIcon, PageHeader. `Components/Forms`: FormField (label/help/error + aria wiring), LocaleTabs (ar/en/he with error dots). KTUI **JavaScript is not loaded** — open/close, tabs, drawers and dropdowns are Vue state toggling the KTUI classes (`.open`, `.active`), which avoids DOM conflicts with Vue.

## Accessibility baseline
Skip link, visible focus rings, 44 px touch targets, labelled icon buttons, `aria-live` price + toasts, `aria-pressed` on option buttons, form errors linked via `aria-describedby`, `prefers-reduced-motion` respected.

## Images
`<ResponsiveImage>` → `<picture>` AVIF + WebP srcsets (400/800/1600 w) with original fallback while conversions are pending; lazy by default, `priority` for above-the-fold.

## Next design steps
Real logo upload (Settings → General), hero photography/banners, brand strip once brands are curated, a WYSIWYG for descriptions, browser-based visual QA on mobile/tablet/desktop in all three languages.
