<?php

namespace App\Tables\Admin;

use App\Models\User;
use App\Support\DataTable;
use App\Tables\BulkAction;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;
use Spatie\Permission\Models\Role;

/**
 * Staff accounts (never customers). Bulk activate / deactivate / delete go through the UserPolicy, so nobody
 * can disable or delete an account more powerful than theirs, or themselves.
 *
 * @extends Table<User>
 */
class UsersTable extends Table
{
    public static function key(): string
    {
        return 'users';
    }

    public static function permission(): string
    {
        return 'users.view';
    }

    protected function title(): string
    {
        return __('admin.staff');
    }

    protected function route(): string
    {
        return 'admin.users.index';
    }

    protected function query(): Builder
    {
        return User::query()->with(['roles', 'media'])
            ->where(fn (Builder $q) => $q->whereHas('roles', fn (Builder $r) => $r->where('name', '!=', 'customer'))->orWhereDoesntHave('roles'));
    }

    protected function search(Builder $query, string $term): void
    {
        $like = DataTable::like($term);
        $query->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like);
    }

    protected function searchPlaceholder(): string
    {
        return __('admin.search_users');
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }

    public static function roleLabel(string $name): string
    {
        return Lang::has("permissions.roles.{$name}") ? __("permissions.roles.{$name}") : $name;
    }

    protected function columns(): array
    {
        return [
            Column::text('name', __('admin.name'))->avatar('avatar')->link('edit_url')->sub('email')->sortable()->fixed(),
            Column::text('roles', __('admin.roles_assigned'))->field('role_labels'),
            Column::boolean('is_active', __('common.status'), __('admin.active'), __('admin.inactive')),
            Column::boolean('two_factor', __('admin.two_factor'), __('admin.enabled'), __('admin.disabled'))->hidden(),
            Column::date('created_at', __('admin.created'))->sortable()->align('end'),
        ];
    }

    protected function filters(): array
    {
        $roles = Role::query()->where('name', '!=', 'customer')->orderBy('name')->pluck('name')->mapWithKeys(fn (string $n) => [$n => self::roleLabel($n)])->all();

        return [
            Filter::select('role', __('admin.role'), $roles, fn (Builder $q, string $v) => $q->whereHas('roles', fn (Builder $r) => $r->where('name', $v))),
            Filter::select('status', __('common.status'), ['active' => __('admin.active'), 'inactive' => __('admin.inactive')],
                fn (Builder $q, string $v) => $q->where('is_active', $v === 'active')),
            Filter::select('two_factor', __('admin.two_factor'), ['on' => __('admin.enabled'), 'off' => __('admin.disabled')],
                fn (Builder $q, string $v) => $v === 'on' ? $q->whereNotNull('two_factor_confirmed_at') : $q->whereNull('two_factor_confirmed_at')),
        ];
    }

    protected function extraSorts(): array
    {
        return ['email'];
    }

    protected function actions(): array
    {
        return ['edit' => 'edit_url', 'delete' => 'delete_url', 'label' => 'name', 'activity' => 'user'];
    }

    protected function bulkActions(): array
    {
        $guarded = fn (string $ability, \Closure $do) => function (Model $target) use ($ability, $do): ?string {
            /** @var User $target */
            if ($target->is($this->user()) || ! $this->user()->can($ability, $target)) {
                return __('table.bulk_not_allowed');
            }
            $do($target);

            return null;
        };

        return [
            BulkAction::make('activate', __('table.activate'), 'users.update', $guarded('update', fn (User $u) => $u->forceFill(['is_active' => true])->save())),
            BulkAction::make('deactivate', __('table.deactivate'), 'users.update', $guarded('update', fn (User $u) => $u->forceFill(['is_active' => false])->save())),
            BulkAction::make('delete', __('table.delete_selected'), 'users.delete', $guarded('delete', fn (User $u) => $u->delete()))->destructive(),
        ];
    }

    /** @param User $u */
    protected function row(Model $u): array
    {
        $actor = $this->user();
        $roles = $u->roles->pluck('name');

        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'avatar' => $u->avatar(),
            'is_active' => $u->is_active,
            'roles' => $roles,
            'role_labels' => $roles->map(fn (string $r) => self::roleLabel($r))->implode('، '),
            'two_factor' => $u->two_factor_confirmed_at !== null,
            'created_at' => $u->created_at?->format('Y-m-d'),
            'edit_url' => $actor->can('update', $u) ? route('admin.users.edit', $u) : null,
            'delete_url' => $actor->can('delete', $u) ? route('admin.users.destroy', $u) : null,
        ];
    }
}
