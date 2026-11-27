<?php

namespace App\Tables\Admin;

use App\Support\DataTable;
use App\Support\Permissions;
use App\Tables\BulkAction;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

/**
 * Roles. Bulk delete follows the single-role rules: never super-admin or a built-in role, never a role that
 * still has users.
 *
 * @extends Table<Role>
 */
class RolesTable extends Table
{
    public static function key(): string
    {
        return 'roles';
    }

    public static function permission(): string
    {
        return 'roles.view';
    }

    protected function title(): string
    {
        return __('permissions.roles_list');
    }

    protected function route(): string
    {
        return 'admin.roles.index';
    }

    protected function query(): Builder
    {
        return Role::query()->where('guard_name', 'web')->withCount(['users', 'permissions']);
    }

    protected function search(Builder $query, string $term): void
    {
        $query->where('name', 'like', DataTable::like($term));
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function columns(): array
    {
        return [
            Column::text('name', __('permissions.role'))->field('label')->link('edit_url')->sub('name')->sortable()->fixed(),
            Column::number('permissions_count', __('permissions.permissions_count'))->field('permissions_label')->sortable()->align('start'),
            Column::number('users_count', __('permissions.users_count'))->sortable(),
            Column::badge('type', __('permissions.type'), [
                'built_in' => [__('permissions.built_in'), 'secondary'],
                'custom' => [__('permissions.custom'), 'primary'],
            ]),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('type', __('permissions.type'), ['built_in' => __('permissions.built_in'), 'custom' => __('permissions.custom')],
                fn (Builder $q, string $v) => $v === 'built_in'
                    ? $q->whereIn('name', array_keys(Permissions::defaultRoles()))
                    : $q->whereNotIn('name', array_keys(Permissions::defaultRoles()))),
        ];
    }

    protected function extraSorts(): array
    {
        return ['created_at'];
    }

    protected function actions(): array
    {
        return ['view' => 'view_url', 'edit' => 'edit_url', 'delete' => 'delete_url', 'label' => 'label', 'activity' => 'role'];
    }

    protected function bulkActions(): array
    {
        return [
            BulkAction::make('delete', __('table.delete_selected'), 'roles.delete', function (Model $role): ?string {
                /** @var Role $role */
                if ($role->name === Permissions::SUPER_ADMIN || Permissions::isBuiltIn($role->name)) {
                    return __('permissions.built_in_locked');
                }
                if ($role->users()->exists()) {
                    return __('permissions.role_in_use');
                }
                activity('users')->performedOn($role)->event('deleted')
                    ->withProperties(['label' => $role->name, 'revoked' => $role->permissions()->pluck('name')->all()])
                    ->log('deleted');
                $role->delete();

                return null;
            })->destructive(),
        ];
    }

    private function label(string $name): string
    {
        $key = "permissions.roles.{$name}";

        return __($key) === $key ? $name : __($key);
    }

    /** @param Role $role */
    protected function row(Model $role): array
    {
        $user = $this->user();
        $locked = $role->name === Permissions::SUPER_ADMIN;
        $builtIn = Permissions::isBuiltIn($role->name);
        $total = count(Permissions::all());
        $count = $locked ? $total : (int) $role->getAttribute('permissions_count');

        return [
            'id' => $role->id,
            'name' => $role->name,
            'label' => $this->label($role->name),
            'users_count' => (int) $role->getAttribute('users_count'),
            'permissions_count' => $count,
            'permissions_label' => __('permissions.selected_of', ['count' => $count, 'total' => $total]),
            'built_in' => $builtIn,
            'type' => $builtIn ? 'built_in' : 'custom',
            'locked' => $locked,
            'created_at' => $role->created_at?->translatedFormat('Y-m-d'),
            'edit_url' => ! $locked && $user->can('roles.update') ? route('admin.roles.edit', $role) : null,
            'view_url' => $locked && $user->can('roles.view') ? route('admin.roles.edit', $role) : null,
            'delete_url' => ! $builtIn && $user->can('roles.delete') ? route('admin.roles.destroy', $role) : null,
        ];
    }
}
