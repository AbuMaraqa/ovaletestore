<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public const PERMISSIONS = [
        'admin.access',
        'products.view', 'products.create', 'products.update', 'products.delete',
        'categories.manage', 'attributes.manage', 'brands.manage',
        'orders.view', 'orders.update',
        'companies.manage', 'pos.access',
        'users.manage',
        'settings.manage',
    ];

    public const ROLES = [
        'super-admin' => [], // bypasses all checks via Gate::before
        'admin' => self::PERMISSIONS,
        'product-manager' => ['admin.access', 'products.view', 'products.create', 'products.update', 'products.delete', 'categories.manage', 'attributes.manage', 'brands.manage'],
        'content-manager' => ['admin.access', 'products.view', 'products.update', 'categories.manage', 'brands.manage', 'settings.manage'],
        'customer' => [], // storefront account only — no admin access
        'order-manager' => ['admin.access', 'orders.view', 'orders.update', 'products.view', 'companies.manage'],
        // iPad POS only: places on-account orders for companies; no admin panel.
        'sales-rep' => ['pos.access'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (self::ROLES as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($role === 'super-admin' ? self::PERMISSIONS : $permissions);
        }
    }
}
