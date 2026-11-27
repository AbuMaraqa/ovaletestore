<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Tables\Admin\UsersTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /** Staff accounts (anyone with a role other than "customer"); customers have their own screen. */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        return Inertia::render('Admin/Users/Index', (new UsersTable($request))->props('users'));
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Admin/Users/Form', ['user' => null, 'roles' => $this->assignableRoles()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);
        $data = $this->validated($request);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'is_active' => $data['is_active'] ?? true,
        ]);
        $this->syncRolesLogged($user, $data['roles'] ?? []);

        return to_route('admin.users.index')->with('success', __('admin.created'));
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('Admin/Users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => $user->is_active,
                'roles' => $user->roles()->pluck('name'),
                'is_self' => $request->user()->is($user),
            ],
            'roles' => $this->assignableRoles(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        $data = $this->validated($request, $user);
        $self = $request->user()->is($user);

        // Lock-out guard: nobody can deactivate their own account (they would be signed out at once).
        if ($self && ! (bool) ($data['is_active'] ?? true)) {
            throw ValidationException::withMessages(['is_active' => __('admin.cannot_deactivate_self')]);
        }

        $user->fill(collect($data)->only(['name', 'email', 'phone', 'is_active'])->all());
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        // Own roles are never changed from this form (no self-demotion), and super-admin — which the
        // form cannot offer — is always preserved instead of being silently stripped.
        if (! $self) {
            $roles = $data['roles'] ?? [];
            if ($user->hasRole('super-admin')) {
                $roles[] = 'super-admin';
            }
            $this->syncRolesLogged($user, $roles);
        }

        return back()->with('success', __('admin.updated'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);
        $user->delete();

        return to_route('admin.users.index')->with('success', __('admin.deleted'));
    }

    /** Role changes are pivot rows (no model event): record them in the audit trail by hand. */
    private function syncRolesLogged(User $user, array $roles): void
    {
        $before = $user->roles()->pluck('name')->sort()->values()->all();
        $user->syncRoles($roles);
        $after = $user->roles()->pluck('name')->sort()->values()->all();

        if ($before !== $after) {
            activity('users')->event('roles_changed')->performedOn($user)
                ->withChanges(['attributes' => ['roles' => $after], 'old' => ['roles' => $before]])
                ->withProperties(['label' => $user->name])
                ->log('roles_changed');
        }
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            // super-admin can never be granted through the UI (privilege escalation guard).
            'roles.*' => ['string', Rule::in($this->assignableRoles()->all())],
        ]);
    }

    /**
     * Roles the current user may hand out: never super-admin, and only roles whose every permission
     * the actor holds themselves (someone with users.create cannot mint an admin).
     */
    private function assignableRoles()
    {
        $actor = request()->user();

        return Role::query()->where('name', '!=', 'super-admin')->with('permissions')->orderBy('name')->get()
            ->filter(fn (Role $role) => $role->permissions->every(fn ($p) => $actor->can($p->name)))
            ->pluck('name')->values();
    }
}
