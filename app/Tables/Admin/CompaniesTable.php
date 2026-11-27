<?php

namespace App\Tables\Admin;

use App\Models\Company;
use App\Support\DataTable;
use App\Support\Money;
use App\Tables\BulkAction;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * B2B companies on account. Bulk: activate / deactivate, delete (only without history).
 *
 * @extends Table<Company>
 */
class CompaniesTable extends Table
{
    public static function key(): string
    {
        return 'companies';
    }

    public static function permission(): string
    {
        return 'companies.view';
    }

    protected function title(): string
    {
        return __('admin.companies');
    }

    protected function route(): string
    {
        return 'admin.companies.index';
    }

    protected function query(): Builder
    {
        return Company::query()->withBalance()->withCount('orders');
    }

    protected function search(Builder $query, string $term): void
    {
        $like = DataTable::like($term);
        $query->where('name', 'like', $like)->orWhere('phone', 'like', $like)
            ->orWhere('city', 'like', $like)->orWhere('contact_name', 'like', $like);
    }

    protected function searchPlaceholder(): string
    {
        return __('admin.search_companies');
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function columns(): array
    {
        return [
            Column::text('name', __('admin.company'))->link('show_url')->sub('city')->sortable()->fixed(),
            Column::text('contact_name', __('admin.contact_name')),
            Column::text('phone', __('admin.phone'))->ltr(),
            Column::number('orders_count', __('admin.orders'))->sortable(),
            Column::money('balance', __('admin.balance')),
            Column::boolean('is_active', __('common.status'), __('admin.active'), __('admin.inactive')),
        ];
    }

    protected function filters(): array
    {
        $cities = Company::query()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city')->mapWithKeys(fn (string $c) => [$c => $c])->all();

        return [
            Filter::select('status', __('common.status'), ['active' => __('admin.active'), 'inactive' => __('admin.inactive')],
                fn (Builder $q, string $v) => $q->where('is_active', $v === 'active')),
            Filter::select('city', __('admin.city'), $cities, fn (Builder $q, string $v) => $q->where('city', $v)),
        ];
    }

    protected function extraSorts(): array
    {
        return ['created_at', 'id'];
    }

    protected function actions(): array
    {
        return ['view' => 'show_url', 'edit' => 'edit_url', 'delete' => 'delete_url', 'label' => 'name', 'activity' => 'company'];
    }

    protected function bulkActions(): array
    {
        return [
            BulkAction::toggle('activate', __('table.activate'), 'companies.update', 'is_active', true),
            BulkAction::toggle('deactivate', __('table.deactivate'), 'companies.update', 'is_active', false),
            BulkAction::make('delete', __('table.delete_selected'), 'companies.delete', function (Model $company): ?string {
                /** @var Company $company */
                if ($company->orders()->exists() || $company->payments()->exists()) {
                    return __('admin.company_has_history');
                }
                $company->delete();

                return null;
            })->destructive(),
        ];
    }

    /** @param Company $c */
    protected function row(Model $c): array
    {
        $user = $this->user();

        return [
            'id' => $c->id,
            'name' => $c->name,
            'contact_name' => $c->contact_name,
            'phone' => $c->phone,
            'city' => $c->city,
            'is_active' => $c->is_active,
            'orders_count' => $c->orders_count,
            'balance' => Money::format($c->balance()),
            'has_debt' => $c->balance() > 0,
            'show_url' => route('admin.companies.show', $c),
            'edit_url' => $user->can('companies.update') ? route('admin.companies.edit', $c) : null,
            'delete_url' => $user->can('companies.delete') ? route('admin.companies.destroy', $c) : null,
        ];
    }
}
