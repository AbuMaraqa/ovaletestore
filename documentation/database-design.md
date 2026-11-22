# Database Design

Portable schema (SQLite locally, MySQL 8 in production). Money = integer minor units (agorot). All FKs constrained.

## Catalog
| Table | Key columns | Notes |
|---|---|---|
| `categories` | parent_id (self, nullOnDelete), sort_order, is_active, source_id (unique) | hierarchical |
| `category_translations` | category_id, locale, name, **slug**, description, meta_* | unique(category_id, locale), unique(locale, slug) |
| `brands` / `brand_translations` | is_active, is_featured / name, slug, description | |
| `products` | type (simple/variable), status (draft/published/archived), brand_id, is_featured, published_at, source_id, soft deletes | |
| `product_translations` | name, slug, short_description, description (sanitized HTML), meta_title, meta_description | unique(locale, slug) |
| `category_product` | is_primary | pivot |
| `attributes` | product_id NULL = global, else custom; code; display_type | unique(product_id, code) |
| `attribute_translations` | name | |
| `attribute_terms` / `attribute_term_translations` | code, color_hex, sort_order / name | |
| `product_attributes` | product_id, attribute_id, used_for_variations, is_visible | unique(product_id, attribute_id) |
| `product_attribute_term` | product_attribute_id, attribute_term_id | terms a product offers |
| `product_variants` | **signature** (sorted term ids), sku (unique, nullable), regular_price, sale_price, sale window, manage_stock, stock_quantity, stock_status, weight/dimensions, is_active, is_default | unique(product_id, signature) |
| `product_variant_term` | product_variant_id, attribute_term_id | the combination — relational, no JSON |

**Purchasable unit = variant.** Simple product → one variant with signature `''`. Variable product → one variant per combination; `signature` makes duplicates impossible at DB level.

## Commerce
| Table | Notes |
|---|---|
| `carts` (token uuid, user_id unique) / `cart_items` (variant, quantity; unique per cart) | carts never store prices |
| `orders` | number (unique, `OV-yymmdd-XXXXX`), status, customer & delivery fields, currency, subtotal/discount/shipping/total, locale, placed_at |
| `order_items` | **snapshots**: name, variant_label, sku, unit_price, line_total; product/variant FKs nullOnDelete |
| `order_notes` | internal notes + author |
| `redirects` | from_path (decoded, unique) → to_path, status, hits |

## Package tables
`media` (spatie/medialibrary), `roles/permissions/*` (spatie/permission), `settings` (spatie/settings), `activity_log`, `passkeys`/2FA columns (Fortify), `jobs`, `cache`, `sessions`.

## Indexes worth knowing
products(status, published_at), products(status, is_featured), product_variants(product_id, is_active), product_variants(regular_price), product_translations(locale, name), orders(status, placed_at), orders(customer_phone).
