<?php

namespace App\Tables\Admin;

use App\Actions\Orders\ChangeOrderStatus;
use App\Actions\Orders\DeleteOrder;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Support\DataTable;
use App\Support\Money;
use App\Tables\BulkAction;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Orders (web + POS). Bulk: move the selection to a status (same transition rules and restocking as
 * one order), print their shipping labels, delete (only where the order policy allows).
 *
 * @extends Table<Order>
 */
class OrdersTable extends Table
{
    public const STATUS_VARIANTS = [
        'pending' => 'warning', 'confirmed' => 'info', 'processing' => 'primary',
        'shipped' => 'info', 'completed' => 'success', 'cancelled' => 'destructive',
    ];

    public static function key(): string
    {
        return 'orders';
    }

    public static function permission(): string
    {
        return 'orders.view';
    }

    protected function title(): string
    {
        return __('admin.orders');
    }

    protected function route(): string
    {
        return 'admin.orders.index';
    }

    protected function query(): Builder
    {
        return Order::query()->with('user:id,name')->withCount('items');
    }

    protected function search(Builder $query, string $term): void
    {
        $like = DataTable::like($term);
        $query->where('number', 'like', $like)
            ->orWhere('customer_name', 'like', $like)
            ->orWhere('customer_phone', 'like', $like)
            ->orWhere('customer_email', 'like', $like)
            ->orWhere('shipping_city', 'like', $like);
    }

    protected function searchPlaceholder(): string
    {
        return __('admin.search_orders');
    }

    protected function defaultSort(): string
    {
        return '-placed_at';
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function statusBadges(): array
    {
        return collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $s) => [
            $s->value => [__("orders.status_{$s->value}"), self::STATUS_VARIANTS[$s->value]],
        ])->all();
    }

    protected function columns(): array
    {
        return [
            Column::text('number', __('orders.number'))->link('show_url')->ltr()->strong()->chips([['when' => 'is_pos', 'label' => 'POS']])->sortable()->fixed(),
            Column::text('customer_name', __('customers.customer'))->field('customer')->link('customer_url')->sub('phone')->sortable(),
            Column::text('city', __('admin.city')),
            Column::number('items_count', __('admin.items'))->sortable(),
            Column::badge('channel', __('admin.channel'), ['web' => [__('admin.channel_web'), 'secondary'], 'pos' => [__('admin.channel_pos'), 'primary']])->hidden(),
            Column::badge('payment', __('admin.payment'), ['cod' => [__('admin.pay_cod'), 'secondary'], 'on_account' => [__('admin.pay_on_account'), 'info']])->hidden(),
            Column::text('shipping', __('admin.shipping_methods'))->hidden(),
            Column::badge('status', __('common.status'), self::statusBadges())->sortable(),
            Column::money('total', __('admin.total'))->sortable(),
            Column::date('placed_at', __('admin.date'))->field('ago')->title('date')->sortable()->align('end'),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('status', __('common.status'), collect(self::statusBadges())->map(fn ($b) => $b[0])->all(),
                fn (Builder $q, string $v) => $q->where('status', $v)),
            Filter::select('channel', __('admin.channel'), ['web' => __('admin.channel_web'), 'pos' => __('admin.channel_pos')],
                fn (Builder $q, string $v) => $q->where('channel', $v)),
            Filter::select('account', __('customers.account'), ['registered' => __('customers.registered_account'), 'guest' => __('customers.guest')],
                fn (Builder $q, string $v) => $v === 'registered' ? $q->whereNotNull('user_id') : $q->whereNull('user_id')),
            Filter::select('payment', __('admin.payment'), ['cod' => __('admin.pay_cod'), 'on_account' => __('admin.pay_on_account')],
                fn (Builder $q, string $v) => $q->where('payment_method', $v)),
            Filter::select('shipping', __('admin.shipping_methods'),
                ShippingMethod::query()->with('translations')->orderBy('sort_order')->get()->mapWithKeys(fn (ShippingMethod $m) => [$m->id => $m->name])->all(),
                fn (Builder $q, string $v) => $q->where('shipping_method_id', (int) $v)),
            Filter::dateRange(__('admin.date'), 'placed_at'),
        ];
    }

    protected function hiddenFilters(): array
    {
        return [AllowedFilter::exact('company_id'), AllowedFilter::exact('customer', 'user_id')];
    }

    protected function actions(): array
    {
        return ['view' => 'show_url', 'delete' => 'delete_url', 'label' => 'number', 'activity' => 'order'];
    }

    protected function bulkActions(): array
    {
        $statuses = collect([OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Completed, OrderStatus::Cancelled])
            ->map(fn (OrderStatus $to) => BulkAction::make("status_{$to->value}", __('table.mark_as', ['status' => __("orders.status_{$to->value}")]), 'orders.update_status',
                function (Model $order) use ($to): ?string {
                    /** @var Order $order */
                    if ($order->status === $to) {
                        return __('table.already_status');
                    }
                    app(ChangeOrderStatus::class)->handle($order, $to, $this->user());

                    return null;
                })->confirmed());

        return [
            ...$statuses->all(),
            BulkAction::download('labels', __('invoices.print_labels'), 'orders.view', route('admin.orders.labels')),
            BulkAction::make('delete', __('table.delete_selected'), 'orders.delete', function (Model $order): ?string {
                /** @var Order $order */
                if (! $this->user()->can('delete', $order)) {
                    return __('table.cannot_delete_on_account');
                }
                app(DeleteOrder::class)->handle($order);

                return null;
            })->destructive(),
        ];
    }

    /** @param Order $o */
    protected function row(Model $o): array
    {
        $user = $this->user();

        return [
            'id' => $o->id,
            'number' => $o->number,
            'customer' => $o->customer_name,
            'customer_url' => $o->user && $user->can('customers.view') ? route('admin.customers.show', $o->user) : null,
            'phone' => $o->customer_phone,
            'email' => $o->customer_email,
            'city' => $o->shipping_city,
            'shipping' => $o->shipping_method_name,
            'payment' => $o->payment_method->value,
            'status' => $o->status->value,
            'channel' => $o->channel->value,
            'is_pos' => $o->channel->value === 'pos',
            'items_count' => $o->items_count,
            'total' => Money::format($o->total, $o->currency),
            'date' => $o->placed_at->format('Y-m-d H:i'),
            'ago' => $o->placed_at->diffForHumans(),
            'show_url' => route('admin.orders.show', $o),
            'delete_url' => $user->can('delete', $o) ? route('admin.orders.destroy', $o) : null,
        ];
    }
}
