<?php

namespace App\Tables\Admin;

use App\Models\Brand;
use App\Support\DataTable;
use App\Tables\BulkAction;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @extends Table<Brand> */
class BrandsTable extends Table
{
    public static function key(): string
    {
        return 'brands';
    }

    public static function permission(): string
    {
        return 'brands.view';
    }

    protected function title(): string
    {
        return __('admin.brands');
    }

    protected function route(): string
    {
        return 'admin.brands.index';
    }

    protected function query(): Builder
    {
        return Brand::query()->with(['translations', 'media'])->withCount('products');
    }

    protected function search(Builder $query, string $term): void
    {
        $query->whereHas('translations', fn ($t) => $t->where('name', 'like', DataTable::like($term)));
    }

    protected function searchPlaceholder(): string
    {
        return __('admin.search_brands');
    }

    protected function defaultSort(): string
    {
        return 'sort_order';
    }

    protected function columns(): array
    {
        return [
            Column::text('name', __('admin.name'))->image('logo')->link('edit_url')->chips([['when' => 'is_featured', 'label' => __('admin.featured'), 'variant' => 'primary']])->fixed(),
            Column::number('products_count', __('admin.products'))->sortable(),
            Column::boolean('is_active', __('common.status'), __('admin.active'), __('admin.inactive')),
            Column::number('sort_order', __('admin.sort_order'))->sortable()->hidden(),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('status', __('common.status'), ['active' => __('admin.active'), 'inactive' => __('admin.inactive')],
                fn (Builder $q, string $v) => $q->where('is_active', $v === 'active')),
            Filter::select('featured', __('admin.featured_filter'), ['yes' => __('admin.featured'), 'no' => __('common.no')],
                fn (Builder $q, string $v) => $q->where('is_featured', $v === 'yes')),
        ];
    }

    protected function extraSorts(): array
    {
        return ['created_at', 'id'];
    }

    protected function actions(): array
    {
        return ['edit' => 'edit_url', 'delete' => 'delete_url', 'label' => 'name', 'activity' => 'brand'];
    }

    protected function bulkActions(): array
    {
        return [
            BulkAction::toggle('activate', __('table.activate'), 'brands.update', 'is_active', true),
            BulkAction::toggle('deactivate', __('table.deactivate'), 'brands.update', 'is_active', false),
            BulkAction::toggle('feature', __('table.feature'), 'brands.update', 'is_featured', true),
            BulkAction::toggle('unfeature', __('table.unfeature'), 'brands.update', 'is_featured', false),
            BulkAction::make('delete', __('table.delete_selected'), 'brands.delete', function (Model $brand): ?string {
                $brand->delete();

                return null;
            })->destructive(),
        ];
    }

    /** @param Brand $b */
    protected function row(Model $b): array
    {
        $user = $this->user();

        return [
            'id' => $b->id,
            'name' => $b->name,
            'is_active' => $b->is_active,
            'is_featured' => $b->is_featured,
            'sort_order' => $b->sort_order,
            'products_count' => $b->products_count,
            'logo' => $b->getFirstMediaUrl('logo', 'thumb') ?: $b->getFirstMediaUrl('logo') ?: null,
            'edit_url' => $user->can('brands.update') ? route('admin.brands.edit', $b) : null,
            'delete_url' => $user->can('brands.delete') ? route('admin.brands.destroy', $b) : null,
        ];
    }
}
