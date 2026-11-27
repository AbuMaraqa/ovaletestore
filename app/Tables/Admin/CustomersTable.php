<?php

namespace App\Tables\Admin;

use App\Enums\OrderStatus;
use App\Models\User;
use App\Support\DataTable;
use App\Support\Money;
use App\Tables\BulkAction;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Store customers (accounts with only the customer role): orders, spend, status.
 *
 * @extends Table<User>
 */
class CustomersTable extends Table
{
    public static function key(): string
    {
        return 'customers';
    }

    public static function permission(): string
    {
        return 'customers.view';
    }

    protected function title(): string
    {
        return __('customers.title');
    }

    protected function route(): string
    {
        return 'admin.customers.index';
    }

    protected function query(): Builder
    {
        return User::query()->role('customer')
            ->with('media')
            ->withCount('orders')
            ->withSum(['orders as spent' => fn (Builder $q) => $q->where('status', '!=', OrderStatus::Cancelled)], 'base_total')
            ->withMax('orders as last_order_at', 'placed_at');
    }

    protected function search(Builder $query, string $term): void
    {
        $like = DataTable::like($term);
        $query->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like);
    }

    protected function searchPlaceholder(): string
    {
        return __('customers.search');
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }

    protected function columns(): array
    {
        return [
            Column::text('name', __('customers.customer'))->avatar('avatar')->link('show_url')->sub('email')->sortable()->fixed(),
            Column::text('phone', __('admin.phone'))->ltr(),
            Column::number('orders_count', __('customers.orders'))->sortable(),
            Column::money('spent', __('customers.spent'))->sortable(),
            Column::date('last_order_at', __('customers.last_order'))->field('last_order')->sortable(),
            Column::badge('source', __('customers.source'), [
                'woocommerce' => [__('customers.from_woocommerce'), 'secondary'],
                'new' => [__('customers.registered_here'), 'primary'],
            ])->hidden(),
            Column::boolean('is_active', __('common.status'), __('admin.active'), __('admin.inactive')),
            Column::date('created_at', __('customers.registered'))->field('registered')->sortable()->hidden(),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('orders', __('customers.has_orders'), ['with' => __('customers.with_orders'), 'without' => __('customers.without_orders')],
                fn (Builder $q, string $v) => $v === 'with' ? $q->has('orders') : $q->doesntHave('orders')),
            Filter::select('status', __('common.status'), ['active' => __('admin.active'), 'inactive' => __('admin.inactive')],
                fn (Builder $q, string $v) => $q->where('is_active', $v === 'active')),
            Filter::select('source', __('customers.source'), ['woocommerce' => __('customers.from_woocommerce'), 'new' => __('customers.registered_here')],
                fn (Builder $q, string $v) => $v === 'woocommerce' ? $q->whereNotNull('source_id') : $q->whereNull('source_id')),
            Filter::dateRange(__('customers.registered'), 'created_at'),
        ];
    }

    protected function actions(): array
    {
        return ['view' => 'show_url', 'edit' => 'edit_url', 'delete' => 'delete_url', 'label' => 'name', 'activity' => 'user'];
    }

    protected function bulkActions(): array
    {
        return [
            BulkAction::toggle('activate', __('table.activate'), 'customers.update', 'is_active', true),
            BulkAction::toggle('deactivate', __('table.deactivate'), 'customers.update', 'is_active', false),
            BulkAction::make('delete', __('table.delete_selected'), 'customers.delete', function (Model $customer): ?string {
                /** @var User $customer */
                $customer->delete();

                return null;
            })->destructive(),
        ];
    }

    /** @param User $c */
    protected function row(Model $c): array
    {
        $user = $this->user();

        return [
            'id' => $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'phone' => $c->phone,
            'avatar' => $c->avatar(),
            'is_active' => $c->is_active,
            'from_woocommerce' => $c->source_id !== null,
            'source' => $c->source_id !== null ? 'woocommerce' : 'new',
            'orders_count' => (int) $c->getAttribute('orders_count'),
            'spent' => Money::format((int) $c->getAttribute('spent')),
            'last_order' => $c->getAttribute('last_order_at') ? now()->parse($c->getAttribute('last_order_at'))->diffForHumans() : null,
            'registered' => $c->created_at?->format('Y-m-d'),
            'show_url' => route('admin.customers.show', $c),
            'edit_url' => $user->can('customers.update') ? route('admin.customers.edit', $c) : null,
            'delete_url' => $user->can('customers.delete') ? route('admin.customers.destroy', $c) : null,
        ];
    }
}
