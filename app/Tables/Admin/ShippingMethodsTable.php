<?php

namespace App\Tables\Admin;

use App\Enums\ShippingMethodType;
use App\Models\ShippingMethod;
use App\Support\DataTable;
use App\Support\Money;
use App\Tables\BulkAction;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Delivery regions and pickup.
 *
 * @extends Table<ShippingMethod>
 */
class ShippingMethodsTable extends Table
{
    public static function key(): string
    {
        return 'shipping';
    }

    public static function permission(): string
    {
        return 'shipping.view';
    }

    protected function title(): string
    {
        return __('admin.shipping_methods');
    }

    protected function route(): string
    {
        return 'admin.shipping-methods.index';
    }

    protected function query(): Builder
    {
        return ShippingMethod::query()->with('translations')->withCount('orders');
    }

    protected function search(Builder $query, string $term): void
    {
        $like = DataTable::like($term);
        $query->where('code', 'like', $like)->orWhereHas('translations', fn ($t) => $t->where('name', 'like', $like));
    }

    protected function searchPlaceholder(): string
    {
        return __('admin.search_shipping');
    }

    protected function defaultSort(): string
    {
        return 'sort_order';
    }

    /** @return array<string, string> */
    private function types(): array
    {
        return collect(ShippingMethodType::cases())->mapWithKeys(fn ($t) => [$t->value => __("admin.shipping_type_{$t->value}")])->all();
    }

    protected function columns(): array
    {
        return [
            Column::text('name', __('admin.name'))->link('edit_url')->fixed(),
            Column::badge('type', __('admin.shipping_type'), collect($this->types())->map(fn ($l) => [$l, 'secondary'])->all()),
            Column::money('cost', __('admin.shipping_cost'))->field('cost_label')->sortable()->align('start'),
            Column::money('free_over', __('admin.free_over'))->align('start'),
            Column::money('min_order', __('admin.min_order_for_method'))->align('start')->hidden(),
            Column::number('orders_count', __('admin.orders'))->sortable(),
            Column::boolean('is_active', __('common.status'), __('admin.active'), __('admin.inactive')),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('type', __('admin.shipping_type'), $this->types(), fn (Builder $q, string $v) => $q->where('type', $v)),
            Filter::select('status', __('common.status'), ['active' => __('admin.active'), 'inactive' => __('admin.inactive')],
                fn (Builder $q, string $v) => $q->where('is_active', $v === 'active')),
        ];
    }

    protected function extraSorts(): array
    {
        return ['sort_order', 'id'];
    }

    protected function actions(): array
    {
        return ['edit' => 'edit_url', 'delete' => 'delete_url', 'label' => 'name', 'activity' => 'shipping_method'];
    }

    protected function bulkActions(): array
    {
        return [
            BulkAction::toggle('activate', __('table.activate'), 'shipping.update', 'is_active', true),
            BulkAction::toggle('deactivate', __('table.deactivate'), 'shipping.update', 'is_active', false),
            BulkAction::make('delete', __('table.delete_selected'), 'shipping.delete', function (Model $method): ?string {
                $method->delete();

                return null;
            })->destructive(),
        ];
    }

    /** @param ShippingMethod $m */
    protected function row(Model $m): array
    {
        $user = $this->user();

        return [
            'id' => $m->id,
            'name' => $m->name,
            'type' => $m->type->value,
            'cost' => $m->cost > 0 ? Money::format($m->cost) : null,
            'cost_label' => $m->cost > 0 ? Money::format($m->cost) : __('cart.free'),
            'free_over' => $m->free_over !== null ? Money::format($m->free_over) : null,
            'min_order' => $m->min_order !== null ? Money::format($m->min_order) : null,
            'is_active' => $m->is_active,
            'orders_count' => $m->orders_count,
            'edit_url' => $user->can('shipping.update') ? route('admin.shipping-methods.edit', $m) : null,
            'delete_url' => $user->can('shipping.delete') ? route('admin.shipping-methods.destroy', $m) : null,
        ];
    }
}
