<?php

namespace App\Tables\Admin;

use App\Models\Currency;
use App\Support\CurrencyContext;
use App\Support\DataTable;
use App\Support\Money;
use App\Tables\BulkAction;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Store currencies. Bulk: activate / deactivate / delete (never the default one). "Make default" is a row
 * action with a strong confirmation (it converts every stored price).
 *
 * @extends Table<Currency>
 */
class CurrenciesTable extends Table
{
    /** Sample price (base minor units) shown converted, so a wrong rate is obvious. */
    public const SAMPLE = 10000;

    public static function key(): string
    {
        return 'currencies';
    }

    public static function permission(): string
    {
        return 'currencies.view';
    }

    protected function title(): string
    {
        return __('currencies.title');
    }

    protected function route(): string
    {
        return 'admin.currencies.index';
    }

    protected function query(): Builder
    {
        return Currency::query();
    }

    protected function search(Builder $query, string $term): void
    {
        $query->where('code', 'like', DataTable::like($term))->orWhere('name', 'like', DataTable::like($term));
    }

    /** @return list<string> */
    protected function defaultSort(): array
    {
        return ['-is_default', 'sort_order', 'code'];
    }

    protected function columns(): array
    {
        $base = app(CurrencyContext::class)->base();

        return [
            Column::text('name', __('admin.name'))->link('edit_url')->sub('code')->chips([['when' => 'is_default', 'label' => __('currencies.default'), 'variant' => 'primary']])->fixed(),
            Column::text('rate', __('currencies.value_column'))->field('value_label')->ltr()->sortable(),
            Column::money('sample', __('currencies.sample', ['amount' => Money::format(self::SAMPLE, $base->code)]))->align('start'),
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
        return ['code', 'sort_order', 'is_default'];
    }

    protected function actions(): array
    {
        return ['edit' => 'edit_url', 'delete' => 'delete_url', 'label' => 'name', 'activity' => 'currency', 'extra' => 'extra'];
    }

    protected function bulkActions(): array
    {
        $notDefault = fn (Model $c): ?string => $c->getAttribute('is_default') ? __('currencies.cannot_delete_default') : null;

        return [
            BulkAction::toggle('activate', __('table.activate'), 'currencies.update', 'is_active', true),
            BulkAction::make('deactivate', __('table.deactivate'), 'currencies.update', function (Model $c) use ($notDefault): ?string {
                return $notDefault($c) ?? tap(null, fn () => $c->forceFill(['is_active' => false])->save());
            }),
            BulkAction::make('delete', __('table.delete_selected'), 'currencies.delete', function (Model $c) use ($notDefault): ?string {
                return $notDefault($c) ?? tap(null, fn () => $c->delete());
            })->destructive(),
        ];
    }

    /** @param Currency $c */
    protected function row(Model $c): array
    {
        $user = $this->user();
        $base = app(CurrencyContext::class)->base();
        $canDefault = ! $c->is_default && $c->is_active && $user->can('currencies.set_default');
        $value = $c->is_default ? null : '1 '.$c->code.' = '.Money::format((int) round(100 / max((float) $c->rate, 1e-8)), $base->code);

        return [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->label(),
            'symbol' => $c->symbol(),
            'rate' => (float) $c->rate,
            'value' => $value,
            'value_label' => $value ?? '—',
            'sample' => Money::format($c->fromBase(self::SAMPLE), $c->code),
            'is_default' => $c->is_default,
            'is_active' => $c->is_active,
            'edit_url' => $user->can('currencies.update') ? route('admin.currencies.edit', $c) : null,
            'delete_url' => ! $c->is_default && $user->can('currencies.delete') ? route('admin.currencies.destroy', $c) : null,
            'default_url' => $canDefault ? route('admin.currencies.default', $c) : null,
            'extra' => $canDefault ? [[
                'label' => __('currencies.make_default'),
                'icon' => 'star',
                'href' => route('admin.currencies.default', $c),
                'method' => 'patch',
                'confirm' => [
                    'title' => __('currencies.make_default_title', ['code' => $c->code]),
                    'message' => implode("\n\n", [
                        __('currencies.make_default_message', ['code' => $c->code, 'rate' => (string) $value]),
                        __('currencies.make_default_example', ['from' => Money::format(self::SAMPLE, $base->code), 'to' => Money::format($c->fromBase(self::SAMPLE), $c->code)]),
                        __('currencies.make_default_warning'),
                    ]),
                    'confirm' => __('currencies.make_default_confirm'),
                ],
            ]] : [],
        ];
    }
}
