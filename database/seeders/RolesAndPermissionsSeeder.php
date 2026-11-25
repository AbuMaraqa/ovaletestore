<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent: creates missing permissions/roles and removes permissions no longer in the registry.
 * Roles edited from the admin panel keep their edits — an existing built-in role only receives the
 * permissions that are new in this run (so a deploy that adds a button grants it to the right roles).
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $wanted = Permissions::all();
        $existing = Permission::query()->where('guard_name', 'web')->pluck('name')->all();
        $new = array_values(array_diff($wanted, $existing));

        foreach ($new as $name) {
            Permission::query()->create(['name' => $name, 'guard_name' => 'web']);
        }
        Permission::query()->where('guard_name', 'web')->whereNotIn('name', $wanted)->delete();

        foreach (Permissions::defaultRoles() as $name => $permissions) {
            $role = Role::query()->where(['name' => $name, 'guard_name' => 'web'])->first();

            if (! $role) {
                Role::query()->create(['name' => $name, 'guard_name' => 'web'])->syncPermissions($permissions);
            } elseif ($added = array_intersect($permissions, $new)) {
                $role->givePermissionTo($added);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
