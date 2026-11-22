# WooCommerce → Laravel Migration Plan

## Source access (in order of completeness)
| Method | Status | Gives us |
|---|---|---|
| 1. WordPress DB dump (`wp_posts`, `wp_postmeta`, `wp_terms*`, `wp_trp_dictionary_*`) | **needed from owner** | drafts, hidden products, wholesale prices, real stock, TranslatePress EN translations, Yoast-less meta |
| 2. WC REST `wc/v3` with consumer key/secret (read-only) | **needed from owner** | full product data incl. drafts, stock qty, meta_data |
| 3. Public Store API `wc/store/v1` | **available now, implemented** | 859 published products, prices, SKU, categories, original image URLs, descriptions |

The importer is built on a `WooSource` interface-free client with one implementation per source; Store API ships first. When credentials arrive they go in `.env` (`WOO_CONSUMER_KEY`, `WOO_CONSUMER_SECRET`) — never in code.

## Commands
```
php artisan woo:inspect                 # counts & data-quality report from source (no writes)
php artisan woo:migrate --dry-run       # map everything, write nothing, print what would change
php artisan woo:migrate                 # categories → products (+default variant) → redirects
php artisan woo:migrate --images        # queue image download+attach (originals, deduped)
php artisan woo:verify                  # compare source vs DB, write documentation/migration-report.md
```

## Mapping
| Woo | Laravel |
|---|---|
| product `id` | `products.source_id` (idempotency key, unique) |
| name `"عربي.English"` | split on the last `.` that is followed by Latin text → ar/en translations; no English → ar only (fallback) |
| slug (percent-encoded) | decoded → `product_translations.slug` (ar) — URL identical to old site |
| en slug | `Str::slug(englishName)` unique per locale |
| description (HTML) | sanitised HTML → ar description |
| prices.regular/sale (minor units) | default variant `regular_price` / `sale_price` (agorot) |
| sku | default variant sku (duplicates → null + logged) |
| categories | `category_product` (first = primary) |
| category `id`, parent | `categories.source_id`, `parent_id` |
| images[].src | media library `gallery` collection on product; original URL (strip `-scaled`) |

## Idempotency & safety
- Upserts keyed by `source_id`; re-running updates, never duplicates.
- Every product write is one DB transaction.
- Source pages fetched 100 at a time (chunked), never all in memory.
- Images: `ImportProductImage` queued job, 3 tries with backoff; dedupe by `sha1(source_url)` stored in media `custom_properties.source_url_hash`; existing media skipped.
- Checkpoint: last completed source page stored in cache key `woo:migrate:checkpoint` (`--fresh` to restart).
- Errors logged to `storage/logs/woo-migration.log` with source id.

## Image quality policy
Original file kept untouched (media library original). Conversions (queued): `thumb` 400w, `card` 800w, `large` 1600w, each WebP q=82 plus AVIF q=60 for `card/large`, never upscaled; responsive srcset for `large`.
