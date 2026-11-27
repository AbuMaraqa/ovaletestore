<?php

namespace App\Tables\Admin;

use App\Models\Category;
use App\Support\DataTable;
use App\Tables\BulkAction;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\AllowedFilter;

/** @extends Table<Category> */
class CategoriesTable extends Table
{
    public static function key(): string
    {
        return 'categories';
    }

    public static function permission(): string
    {
        return 'categories.view';
    }

    protected function title(): string
    {
        return __('admin.categories');
    }

    protected function route(): string
    {
        return 'admin.categories.index';
    }

    protected function query(): Builder
    {
        return Category::query()->with(['translations', 'media', 'parent.translations'])->withCount('products');
    }

    protected function search(Builder $query, string $term): void
    {
        $query->whereHas('translations', fn ($t) => $t->where('name', 'like', DataTable::like($term)));
    }

    protected function defaultSort(): string
    {
        return 'sort_order';
    }

    protected function columns(): array
    {
        return [
            Column::text('name', __('admin.name'))->image('image')->link('edit_url')->sub('parent')->fixed(),
            Column::number('products_count', __('admin.products'))->sortable(),
            Column::number('sort_order', __('admin.sort_order'))->sortable(),
            Column::boolean('is_active', __('common.status'), __('admin.active'), __('admin.inactive')),
            Column::date('created_at', __('admin.created'))->field('created')->sortable()->hidden(),
        ];
    }

    protected function filters(): array
    {
        $parents = Category::query()->whereNull('parent_id')->with('translations')->ordered()->get()->mapWithKeys(fn (Category $c) => [$c->id => $c->name])->all();

        return [
            Filter::select('level', __('admin.level'), ['top' => __('admin.top_level'), 'sub' => __('admin.subcategory')],
                fn (Builder $q, string $v) => $v === 'top' ? $q->whereNull('parent_id') : $q->whereNotNull('parent_id')),
            Filter::select('parent', __('admin.parent_category'), $parents, fn (Builder $q, string $v) => $q->where('parent_id', (int) $v)),
            Filter::select('status', __('common.status'), ['active' => __('admin.active'), 'inactive' => __('admin.inactive')],
                fn (Builder $q, string $v) => $q->where('is_active', $v === 'active')),
        ];
    }

    protected function extraSorts(): array
    {
        return ['id'];
    }

    protected function hiddenFilters(): array
    {
        return [AllowedFilter::exact('parent_id')];
    }

    protected function actions(): array
    {
        return ['edit' => 'edit_url', 'delete' => 'delete_url', 'label' => 'name', 'activity' => 'category', 'extra' => 'extra'];
    }

    protected function bulkActions(): array
    {
        return [
            BulkAction::toggle('activate', __('table.activate'), 'categories.update', 'is_active', true),
            BulkAction::toggle('deactivate', __('table.deactivate'), 'categories.update', 'is_active', false),
            BulkAction::make('delete', __('table.delete_selected'), 'categories.delete', function (Model $category): ?string {
                $category->delete(); // children become top-level; products keep their other categories

                return null;
            })->destructive(),
        ];
    }

    /** @param Category $c */
    protected function row(Model $c): array
    {
        $user = $this->user();
        $view = $c->is_active ? route('storefront.category', ['slug' => $c->slug]) : null;

        return [
            'id' => $c->id,
            'name' => $c->name,
            'parent' => $c->parent?->name,
            'is_active' => $c->is_active,
            'sort_order' => $c->sort_order,
            'products_count' => $c->products_count,
            'created' => $c->created_at?->format('Y-m-d'),
            'image' => $c->getFirstMediaUrl('image', 'thumb') ?: $c->getFirstMediaUrl('image') ?: null,
            'edit_url' => $user->can('categories.update') ? route('admin.categories.edit', $c) : null,
            'delete_url' => $user->can('categories.delete') ? route('admin.categories.destroy', $c) : null,
            'view_url' => $view,
            'extra' => $view ? [['label' => __('admin.view_store'), 'icon' => 'exit-right-corner', 'href' => $view, 'target' => '_blank']] : [],
        ];
    }
}
