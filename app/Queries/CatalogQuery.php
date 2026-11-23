<?php

namespace App\Queries;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Settings\StoreSettings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Storefront product listing: published products, whitelisted filters/sorts, eager-loaded for cards (no N+1).
 *
 * @phpstan-type CatalogFilters array{q?:string|null, sort?:string|null, in_stock?:bool, on_sale?:bool, category?:Category|null, brand_id?:int|null, brand_ids?:list<int>, price_min?:int|null, price_max?:int|null}
 */
class CatalogQuery
{
    public const SORTS = ['newest', 'price_asc', 'price_desc', 'name'];

    public function __construct(private readonly StoreSettings $settings) {}

    /** @param CatalogFilters $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->query($filters)
            ->paginate($this->settings->products_per_page)
            ->withQueryString();
    }

    /** @param CatalogFilters $filters */
    public function query(array $filters): Builder
    {
        $query = Product::query()
            ->published()
            ->with(['translations', 'activeVariants', 'media', 'categories.translations'])
            ->withMin('activeVariants as min_price', 'regular_price');

        if (! $this->settings->show_out_of_stock || ($filters['in_stock'] ?? false)) {
            $query->whereHas('activeVariants', fn (Builder $q) => $q->where('stock_status', '!=', 'out_of_stock'));
        }

        if ($category = $filters['category'] ?? null) {
            $ids = $category->descendantAndSelfIds();
            $query->whereHas('categories', fn (Builder $q) => $q->whereIn('categories.id', $ids));
        }

        if ($filters['on_sale'] ?? false) {
            $query->whereHas('activeVariants', fn (Builder $q) => $q->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'regular_price'));
        }

        if ($brandId = $filters['brand_id'] ?? null) {
            $query->where('brand_id', $brandId);
        }

        if ($brandIds = $filters['brand_ids'] ?? []) {
            $query->whereIn('brand_id', $brandIds);
        }

        // Price range in minor units, against the price the customer actually pays.
        $min = $filters['price_min'] ?? null;
        $max = $filters['price_max'] ?? null;
        if ($min !== null || $max !== null) {
            $query->whereHas('activeVariants', function (Builder $q) use ($min, $max) {
                $price = 'CASE WHEN sale_price IS NOT NULL AND sale_price < regular_price THEN sale_price ELSE regular_price END';
                $q->when($min !== null, fn ($q) => $q->whereRaw("{$price} >= ?", [$min]))
                    ->when($max !== null, fn ($q) => $q->whereRaw("{$price} <= ?", [$max]));
            });
        }

        if ($term = trim((string) ($filters['q'] ?? ''))) {
            $this->applySearch($query, $term);
        }

        return $this->applySort($query, $filters['sort'] ?? 'newest');
    }

    private function applySearch(Builder $query, string $term): void
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($term, 0, 100)).'%';

        $query->where(function (Builder $q) use ($like) {
            $q->whereHas('translations', fn (Builder $t) => $t->where('name', 'like', $like))
                ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', 'like', $like));
        });
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'price_asc' => $query->orderBy('min_price')->orderByDesc('id'),
            'price_desc' => $query->orderByDesc('min_price')->orderByDesc('id'),
            'name' => $query->orderBy(
                ProductTranslation::query()->select('name')
                    ->whereColumn('product_translations.product_id', 'products.id')
                    ->whereIn('locale', [app()->getLocale(), 'ar'])
                    ->orderByRaw('locale = ? desc', [app()->getLocale()])
                    ->limit(1),
            ),
            default => $query->orderByDesc('is_featured')->orderByDesc('published_at')->orderByDesc('id'),
        };
    }
}
