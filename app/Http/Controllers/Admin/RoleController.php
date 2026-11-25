<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\DataTable;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Roles = named sets of permissions (one per button). super-admin bypasses every check and is locked;
 * built-in roles can be re-scoped but not renamed or deleted. Nobody can grant a permission they lack.
 */
class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('roles.view');

        $roles = QueryBuilder::for(Role::query()->where('guard_name', 'web')->withCount(['users', 'permissions']))
            ->allowedFilters(...[
                AllowedFilter::callback('search', fn (Builder $q, $term) => $q->where('name', 'like', DataTable::like($term))),
                AllowedFilter::callback('type', fn (Builder $q, $type) => $type === 'built_in'
                    ? $q->whereIn('name', array_keys(Permissions::defaultRoles()))
                    : $q->whereNotIn('name', array_keys(Permissions::defaultRoles()))),
            ])
            ->allowedSorts(...['name', 'users_count', 'permissions_count', 'created_at'])
            ->defaultSort('name')
            ->paginate(DataTable::perPage($request))
            ->withQueryString()
            ->through(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $this->label($role->name),
                'users_count' => $role->users_count,
                'permissions_count' => $role->name === Permissions::SUPER_ADMIN ? count(Permissions::all()) : $role->permissions_count,
                'built_in' => Permissions::isBuiltIn($role->name),
                'locked' => $role->name === Permissions::SUPER_ADMIN,
                'created_at' => $role->created_at?->translatedFormat('Y-m-d'),
            ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'state' => DataTable::state($request),
            'total_permissions' => count(Permissions::all()),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('roles.create');

        return Inertia::render('Admin/Roles/Form', ['role' => null, ...$this->formOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('roles.create');
        $data = $this->validated($request);

        $role = DB::transaction(function () use ($data) {
            $role = Role::query()->create(['name' => $data['name'], 'guard_name' => 'web']);
            $role->syncPermissions($data['permissions']);

            return $role;
        });
        $this->log($role, 'created', [], $data['permissions']);

        return to_route('admin.roles.edit', $role)->with('success', __('admin.created'));
    }

    public function edit(Role $role): Response
    {
        $this->authorize('roles.view');

        return Inertia::render('Admin/Roles/Form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $this->label($role->name),
                'built_in' => Permissions::isBuiltIn($role->name),
                'locked' => $role->name === Permissions::SUPER_ADMIN,
                'permissions' => $role->name === Permissions::SUPER_ADMIN ? Permissions::all() : $role->permissions()->pluck('name'),
                'users_count' => $role->users()->count(),
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('roles.update');
        $this->guardLocked($role);
        $data = $this->validated($request, $role);

        $before = $role->permissions()->pluck('name')->all();
        $oldName = $role->name;
        DB::transaction(function () use ($role, $data) {
            if (! Permissions::isBuiltIn($role->name)) {
                $role->update(['name' => $data['name']]);
            }
            $role->syncPermissions($data['permissions']);
        });
        $this->log($role, 'updated', $before, $data['permissions'], $oldName);

        return back()->with('success', __('admin.updated'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('roles.delete');
        $this->guardLocked($role);

        if (Permissions::isBuiltIn($role->name)) {
            return back()->with('error', __('permissions.built_in_locked'));
        }
        if ($role->users()->exists()) {
            return back()->with('error', __('permissions.role_in_use'));
        }

        $this->log($role, 'deleted', $role->permissions()->pluck('name')->all(), []);
        $role->delete();

        return to_route('admin.roles.index')->with('success', __('admin.deleted'));
    }

    private function formOptions(): array
    {
        $user = request()->user();

        return [
            'groups' => collect(Permissions::GROUPS)->map(fn (array $permissions, string $group) => [
                'key' => $group,
                'label' => __("permissions.groups.{$group}"),
                'permissions' => array_map(fn (string $p) => [
                    'name' => $p,
                    'label' => __('permissions.items.'.str_replace('.', '_', $p)),
                    // Privilege-escalation guard (also enforced on save): only what you have yourself.
                    'grantable' => $user->can($p),
                ], $permissions),
            ])->values(),
        ];
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'not_in:'.Permissions::SUPER_ADMIN,
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role?->id)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Permissions::all())],
        ]);
        $data['permissions'] = array_values(array_unique($data['permissions'] ?? []));

        $user = $request->user();
        $current = $role ? $role->permissions()->pluck('name')->all() : [];
        // Granting a permission you lack is refused; ones you lack that the role already has are kept as they are.
        $added = array_diff($data['permissions'], $current);
        $removed = array_diff($current, $data['permissions']);
        foreach ([...$added, ...$removed] as $permission) {
            if (! $user->can($permission)) {
                throw ValidationException::withMessages(['permissions' => __('permissions.cannot_grant', ['permission' => $permission])]);
            }
        }

        return $data;
    }

    private function guardLocked(Role $role): void
    {
        abort_if($role->name === Permissions::SUPER_ADMIN, 403);
    }

    private function label(string $name): string
    {
        $key = "permissions.roles.{$name}";

        return __($key) === $key ? $name : __($key);
    }

    /** Pivot changes fire no model event: record which permissions were granted / revoked by hand. */
    private function log(Role $role, string $event, array $before, array $after, ?string $oldName = null): void
    {
        $granted = array_values(array_diff($after, $before));
        $revoked = array_values(array_diff($before, $after));
        $renamed = $oldName !== null && $oldName !== $role->name;
        if ($event === 'updated' && ! $granted && ! $revoked && ! $renamed) {
            return;
        }

        activity('users')->event($event)->performedOn($role)
            ->withChanges([
                'attributes' => array_filter(['name' => $renamed ? $role->name : null, 'permissions' => $granted ?: null]),
                'old' => array_filter(['name' => $renamed ? $oldName : null, 'permissions' => $revoked ?: null]),
            ])
            ->withProperties(['label' => $this->label($role->name)])
            ->log("role_{$event}");
    }
}
