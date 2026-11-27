<?php

namespace App\Tables\Admin;

use App\Actions\Products\BulkUpdateProducts;
use App\Enums\ProductStatus;
use App\Enums\StockStatus;
use App\Http\Resources\Admin\ProductListResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\DataTable;
use App\Tables\BulkAction;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog products. Bulk: publish / draft / archive, feature / unfeature, delete (soft).
 *
 * @extends Table<Product>
 */
class ProductsTable extends Table
{
    public static function key(): string
    {
        return 'products';
    }

    public static function permission(): string
    {
        return 'products.view';
    }

    protected function title(): string
    {
        return __('admin.products');
    }

    protected function route(): string
    {
        return 'admin.products.index';
    }

    protected function query(): Builder
    {
        return Product::query()->with(['translations', 'variants', 'media', 'categories.translations', 'brand.translations']);
    }

    protected function search(Builder $query, string $term): void
    {
        $like = DataTable::like($term);
        $query->whereHas('translations', fn ($t) => $t->where('name', 'like', $like))
            ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $like));
    }

    protected function searchPlaceholder(): string
    {
        return __('admin.search_products');
    }

    protected function columns(): array
    {
        return [
            Column::text('name', __('admin.name'))->image('thumb')->link('edit_url')->chips([
                ['when' => 'is_variable', 'label' => __('admin.type_variable'), 'variant' => 'info', 'count' => 'variants_count'],
                ['when' => 'is_featured', 'label' => __('admin.featured'), 'variant' => 'primary'],
            ])->fixed(),
            Column::text('sku', __('admin.sku'))->ltr()->mono()->muted(),
            Column::money('price', __('admin.price'))->align('start'),
            Column::badge('stock', __('admin.stock'), ['in' => [__('admin.in_stock'), 'success'], 'out' => [__('admin.out_of_stock'), 'destructive']])->field('stock_state'),
            Column::text('categories', __('admin.categories'))->muted()->truncate(),
            Column::text('brand', __('admin.brand'))->hidden(),
            Column::badge('status', __('common.status'), [
                'published' => [__('admin.status_published'), 'success'],
                'draft' => [__('admin.status_draft'), 'warning'],
                'archived' => [__('admin.status_archived'), 'secondary'],
            ])->sortable(),
            Column::date('created_at', __('admin.created'))->sortable()->hidden(),
            Column::date('updated_at', __('admin.last_updated'))->sortable()->align('end'),
        ];
    }

    protected function filters(): array
    {
        $categories = Category::query()->with('translations')->ordered()->get()
            ->mapWithKeys(fn (Category $c) => [$c->id => ($c->parent_id ? '— ' : '').$c->name])->all();

        return [
            Filter::select('status', __('common.status'), collect(ProductStatus::cases())->mapWithKeys(fn ($s) => [$s->value => __("admin.status_{$s->value}")])->all(),
                fn (Builder $q, string $v) => $q->where('status', $v)),
            Filter::select('category', __('admin.categories'), $categories,
                fn (Builder $q, string $v) => $q->whereHas('categories', fn ($c) => $c->where('categories.id', (int) $v))),
            Filter::select('brand', __('admin.brand'), Brand::query()->with('translations')->orderBy('sort_order')->get()->mapWithKeys(fn (Brand $b) => [$b->id => $b->name])->all(),
                fn (Builder $q, string $v) => $q->where('brand_id', (int) $v)),
            Filter::select('type', __('admin.type'), ['simple' => __('admin.type_simple'), 'variable' => __('admin.type_variable')],
                fn (Builder $q, string $v) => $q->where('type', $v)),
            Filter::select('stock', __('admin.stock'), ['in' => __('admin.in_stock'), 'out' => __('admin.out_of_stock')],
                fn (Builder $q, string $v) => $v === 'out'
                    ? $q->whereDoesntHave('variants', fn ($w) => $w->where('stock_status', '!=', StockStatus::OutOfStock))
                    : $q->whereHas('variants', fn ($w) => $w->where('stock_status', '!=', StockStatus::OutOfStock))),
            Filter::select('featured', __('admin.featured_filter'), ['yes' => __('admin.featured'), 'no' => __('common.no')],
                fn (Builder $q, string $v) => $q->where('is_featured', $v === 'yes')),
            Filter::dateRange(__('admin.created'), 'created_at'),
        ];
    }

    protected function extraSorts(): array
    {
        return ['id', 'is_featured'];
    }

    protected function actions(): array
    {
        return ['edit' => 'edit_url', 'delete' => 'delete_url', 'label' => 'name', 'activity' => 'product', 'extra' => 'extra'];
    }

    protected function bulkActions(): array
    {
        $status = fn (string $action) => function (Model $product) use ($action): ?string {
            app(BulkUpdateProducts::class)->handle($action, [(int) $product->getKey()]);

            return null;
        };

        return [
            BulkAction::make('publish', __('admin.status_published'), 'products.update', $status('publish')),
            BulkAction::make('draft', __('admin.status_draft'), 'products.update', $status('draft')),
            BulkAction::make('archive', __('admin.status_archived'), 'products.update', $status('archive')),
            BulkAction::toggle('feature', __('table.feature'), 'products.update', 'is_featured', true),
            BulkAction::toggle('unfeature', __('table.unfeature'), 'products.update', 'is_featured', false),
            BulkAction::make('delete', __('table.delete_selected'), 'products.delete', $status('delete'))->destructive(),
        ];
    }

    /** @param Product $product */
    protected function row(Model $product): array
    {
        $row = (new ProductListResource($product))->resolve($this->request);

        return [
            ...$row,
            'is_variable' => $row['type'] === 'variable',
            'stock_state' => $row['in_stock'] ? 'in' : 'out',
            'extra' => $row['view_url'] ? [['label' => __('admin.view_store'), 'icon' => 'exit-right-corner', 'href' => $row['view_url'], 'target' => '_blank']] : [],
        ];
    }
}
