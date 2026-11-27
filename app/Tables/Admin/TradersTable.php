<?php

namespace App\Tables\Admin;

use App\Actions\Traders\ReviewTraderRequest;
use App\Enums\TraderStatus;
use App\Models\PriceTier;
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
 * Trader accounts and requests. Bulk: approve on a price tier (one action per active tier), reject.
 *
 * @extends Table<User>
 */
class TradersTable extends Table
{
    public static function key(): string
    {
        return 'traders';
    }

    public static function permission(): string
    {
        return 'traders.view';
    }

    protected function title(): string
    {
        return __('traders.title');
    }

    protected function route(): string
    {
        return 'admin.traders.index';
    }

    protected function query(): Builder
    {
        return User::query()->whereNotNull('trader_status')->with(['media', 'priceTier'])
            ->withCount('orders')->withSum('orders as spent', 'base_total');
    }

    protected function search(Builder $query, string $term): void
    {
        $like = DataTable::like($term);
        $query->where('name', 'like', $like)->orWhere('business_name', 'like', $like)
            ->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like);
    }

    protected function searchPlaceholder(): string
    {
        return __('customers.search');
    }

    protected function defaultSort(): string
    {
        return '-trader_requested_at';
    }

    protected function columns(): array
    {
        return [
            Column::text('name', __('traders.trader'))->avatar('avatar')->link('show_url')->sub('contact')->sortable()->fixed(),
            Column::text('business_name', __('traders.business_name'))->sortable(),
            Column::text('tier', __('traders.tier')),
            Column::badge('status', __('common.status'), [
                'pending' => [__('traders.status_pending'), 'warning'],
                'approved' => [__('traders.status_approved'), 'success'],
                'rejected' => [__('traders.status_rejected'), 'destructive'],
            ]),
            Column::number('orders_count', __('customers.orders'))->sortable(),
            Column::money('spent', __('customers.spent'))->sortable()->hidden(),
            Column::date('trader_requested_at', __('traders.requested'))->field('requested')->sortable()->align('end'),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('status', __('common.status'), [
                'pending' => __('traders.status_pending'), 'approved' => __('traders.status_approved'), 'rejected' => __('traders.status_rejected'),
            ], fn (Builder $q, string $v) => $q->where('trader_status', $v)),
            Filter::select('tier', __('traders.tier'), PriceTier::query()->orderBy('sort_order')->get()->mapWithKeys(fn (PriceTier $t) => [$t->id => $t->label()])->all(),
                fn (Builder $q, string $v) => $q->where('price_tier_id', (int) $v)),
        ];
    }

    protected function extraSorts(): array
    {
        return ['name'];
    }

    protected function actions(): array
    {
        return ['view' => 'show_url', 'label' => 'name', 'activity' => 'user'];
    }

    protected function bulkActions(): array
    {
        $review = app(ReviewTraderRequest::class);
        $approve = PriceTier::query()->where('is_active', true)->orderBy('sort_order')->get()
            ->map(fn (PriceTier $tier) => BulkAction::make("approve_{$tier->id}", __('traders.bulk_approve', ['tier' => $tier->label()]), 'traders.approve',
                function (Model $user) use ($review, $tier): ?string {
                    /** @var User $user */
                    if (! $user->hasExactRoles('customer')) {
                        return __('traders.not_customer_account');
                    }
                    $review->handle($user, TraderStatus::Approved, $tier);

                    return null;
                })->confirmed());

        return [
            ...$approve->all(),
            BulkAction::make('reject', __('traders.reject'), 'traders.approve', function (Model $user) use ($review): ?string {
                /** @var User $user */
                $review->handle($user, TraderStatus::Rejected, null);

                return null;
            })->destructive(),
        ];
    }

    /** @param User $u */
    protected function row(Model $u): array
    {
        $user = $this->user();

        return [
            'id' => $u->id,
            'name' => $u->name,
            'business_name' => $u->business_name,
            'email' => $u->email,
            'phone' => $u->phone,
            'contact' => $u->phone ?? $u->email,
            'avatar' => $u->avatar(),
            'status' => $u->trader_status?->value,
            'tier_id' => $u->price_tier_id,
            'tier' => $u->priceTier?->label(),
            'orders_count' => (int) $u->getAttribute('orders_count'),
            'spent' => Money::format((int) $u->getAttribute('spent')),
            'requested' => $u->trader_requested_at?->diffForHumans(),
            'show_url' => $user->can('customers.view') ? route('admin.customers.show', $u) : null,
            'can_review' => $user->can('traders.approve'),
        ];
    }
}
