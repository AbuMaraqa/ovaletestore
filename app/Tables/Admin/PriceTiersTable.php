<?php

namespace App\Tables\Admin;

use App\Models\PriceTier;
use App\Support\DataTable;
use App\Tables\BulkAction;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Trader price lists. Bulk: activate / deactivate, delete (only when no trader uses it).
 *
 * @extends Table<PriceTier>
 */
class PriceTiersTable extends Table
{
    public static function key(): string
    {
        return 'price-tiers';
    }

    public static function permission(): string
    {
        return 'price_tiers.view';
    }

    protected function title(): string
    {
        return __('traders.tiers');
    }

    protected function route(): string
    {
        return 'admin.price-tiers.index';
    }

    protected function query(): Builder
    {
        return PriceTier::query()->withCount(['traders', 'prices']);
    }

    protected function search(Builder $query, string $term): void
    {
        $query->where('code', 'like', DataTable::like($term))->orWhere('name', 'like', DataTable::like($term));
    }

    protected function defaultSort(): string
    {
        return 'sort_order';
    }

    protected function columns(): array
    {
        return [
            Column::text('name', __('admin.name'))->link('edit_url')->sub('code')->fixed(),
            Column::number('discount_percent', __('traders.automatic_discount'))->field('discount_label')->sortable(),
            Column::number('prices_count', __('traders.exact_prices'))->sortable(),
            Column::number('traders_count', __('traders.list'))->sortable(),
            Column::boolean('is_active', __('common.status'), __('admin.active'), __('admin.inactive')),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('status', __('common.status'), ['active' => __('admin.active'), 'inactive' => __('admin.inactive')],
                fn (Builder $q, string $v) => $q->where('is_active', $v === 'active')),
        ];
    }

    protected function extraSorts(): array
    {
        return ['sort_order'];
    }

    protected function actions(): array
    {
        return ['edit' => 'edit_url', 'delete' => 'delete_url', 'label' => 'name', 'activity' => 'price_tier'];
    }

    protected function bulkActions(): array
    {
        return [
            BulkAction::toggle('activate', __('table.activate'), 'price_tiers.update', 'is_active', true),
            BulkAction::toggle('deactivate', __('table.deactivate'), 'price_tiers.update', 'is_active', false),
            BulkAction::make('delete', __('table.delete_selected'), 'price_tiers.delete', function (Model $tier): ?string {
                /** @var PriceTier $tier */
                if ($tier->traders()->exists()) {
                    return __('traders.tier_in_use');
                }
                $tier->delete();

                return null;
            })->destructive(),
        ];
    }

    /** @param PriceTier $tier */
    protected function row(Model $tier): array
    {
        $user = $this->user();
        $discount = (float) $tier->discount_percent;

        return [
            'id' => $tier->id,
            'code' => $tier->code,
            'name' => $tier->label(),
            'discount_percent' => $discount,
            'discount_label' => $discount > 0 ? rtrim(rtrim(number_format($discount, 2), '0'), '.').'%' : null,
            'is_active' => $tier->is_active,
            'traders_count' => (int) $tier->getAttribute('traders_count'),
            'prices_count' => (int) $tier->getAttribute('prices_count'),
            'edit_url' => $user->can('price_tiers.update') ? route('admin.price-tiers.edit', $tier) : null,
            'delete_url' => $user->can('price_tiers.delete') ? route('admin.price-tiers.destroy', $tier) : null,
        ];
    }
}
