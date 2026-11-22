# Source Website Analysis — ovaletestore.com

Analysed: 2026-11-22, via public HTML, `wp-json` discovery and the public WooCommerce **Store API** (`/wp-json/wc/store/v1`). Raw dumps were taken during discovery; numbers below come from those dumps, not estimates.

## Platform
| Item | Finding |
|---|---|
| CMS | WordPress + WooCommerce, Blocksy theme, Elementor |
| Translation | **TranslatePress** (`/en/` prefix). TP stores translations in its own dictionary tables — *not* exposed through any API |
| Notable plugins (REST namespaces) | wholesale (`wwp/v1`), `awdp/v1` (dynamic pricing), LiteSpeed cache, Jetpack, MetaSlider, FluentSMTP, WCPOS (point of sale), Joinchat (WhatsApp) |
| Default language | Arabic, `dir="rtl" lang="ar"` |
| Currency | ILS (₪), 2 minor units |
| Sitemap | WP core `wp-sitemap.xml` (no Yoast/RankMath). `sitemap_index.xml` → 404 |
| robots.txt | Blocks wp-admin & WC upload dirs, points to `wp-sitemap.xml` |

## Catalog (Store API, authoritative public count)
| Metric | Value |
|---|---|
| Products (published, visible) | **859** (`X-WP-Total`) — search engines' "~800" was close |
| Product types | **100% `simple`** — no variable products exist today |
| On sale | 9 |
| Stock | all `in-stock`, stock quantities not managed publicly |
| Images per product | exactly 1 (no galleries) |
| Unique image files | 813 (46 products share an image) |
| Image formats (main) | png 395 · jpg 328 · webp 121 · jpeg 15 |
| `-scaled` images | 2 (WP downscaled originals; original = URL without `-scaled`) |
| Products without SKU | 29 · duplicated SKUs: 7 |
| Short descriptions | 0 · long descriptions: 859 (HTML, Arabic-first) |
| Tags / brands | none |
| Categories | **31, all top-level** (no hierarchy). 26 products uncategorised, 3 in two categories |
| Global attributes | 1 (`pa_الوزن` = "Weight", type *button*), no terms in use |

### Naming convention (important for migration)
Product and category names embed both languages in one string, separated by a dot:
`عجينة سكر احمر1ك.Red Fondant – 1 kg` → ar: `عجينة سكر احمر1ك`, en: `Red Fondant – 1 kg`.
Some names have no English part (e.g. `مفن كاكاو سما SAMA احمدي/ شوال 10 كيلو.`). Hebrew content does not exist.

### Size "variants" are separate products
e.g. *Red Fondant – 1 kg*, *Kunafa dough 400 g / 500 g*, *Cream 5 L*. Grouping them into real variable products is a **post-migration merchandising task** (admin tool), not something to guess automatically.

## URL patterns (must be preserved)
| Page | Pattern |
|---|---|
| Product | `/product/{arabic-percent-encoded-slug}/` |
| Category | `/product-category/{slug}/` |
| English | `/en/...` (TranslatePress mirrors the same slugs) |
| Search | `/?s=term&post_type=product` |
| Pages | `/about-us/`, `/contact-us/`, `/categories/`, `/cart-2/` |

## UX / design observations
- Logo: `cropped-ovalet-logo-2-01.png` (512×512).
- Homepage = Elementor sections; brand shortcuts via search links (Lavazza, Nutella, Lotus, San Martino, Kahan, Chocolake, Aroma Gold, 1882) → these are de-facto **brands** worth modelling.
- WhatsApp chat is the main contact channel.
- Many product photos are screenshots (`لقطة-الشاشة-…`) with inconsistent backgrounds/aspect ratios → storefront must use contained, neutral image frames.

## Migration risks
1. English translations live in TranslatePress tables → without DB access we derive EN from the bilingual name; descriptions remain Arabic-only (EN/HE fall back).
2. Store API exposes only *visible, published* products. Drafts/private/hidden products and wholesale prices need DB or authenticated REST (`wc/v3`) access.
3. Duplicate/missing SKUs → SKU is nullable-unique; duplicates are imported with the SKU cleared and logged.
4. Shared images → media is deduplicated by source URL hash.
5. Arabic slugs must stay byte-identical (decoded) for SEO continuity.

## Recommended improvements
Real brands, real variable products for size families, proper category hierarchy (e.g. *Drinks › Iced / Hot / Mojito / Milkshake*), product search with Arabic normalisation, consistent image presentation, structured data, multilingual sitemap with hreflang.
