<?php

namespace App\Support;

/**
 * The single list of what a staff member can be allowed to do: one permission per button/action.
 * Grouped for the roles screen; labels live in lang/{locale}/permissions.php.
 *
 * Every permission here is checked on the server (policy / authorize); the Vue side only hides buttons.
 */
final class Permissions
{
    /** @var array<string, list<string>> */
    public const GROUPS = [
        'panel' => ['admin.access', 'activity.view', 'analytics.view'],
        'products' => ['products.view', 'products.create', 'products.update', 'products.delete'],
        'categories' => ['categories.view', 'categories.create', 'categories.update', 'categories.delete'],
        'attributes' => ['attributes.view', 'attributes.create', 'attributes.update', 'attributes.delete'],
        'brands' => ['brands.view', 'brands.create', 'brands.update', 'brands.delete'],
        'orders' => ['orders.view', 'orders.update_status', 'orders.delete'],
        'companies' => ['companies.view', 'companies.create', 'companies.update', 'companies.delete', 'companies.payments.create', 'companies.payments.delete'],
        'shipping' => ['shipping.view', 'shipping.create', 'shipping.update', 'shipping.delete'],
        'customers' => ['customers.view', 'customers.create', 'customers.update', 'customers.delete'],
        'traders' => ['traders.view', 'traders.approve'],
        'price_tiers' => ['price_tiers.view', 'price_tiers.create', 'price_tiers.update', 'price_tiers.delete'],
        'currencies' => ['currencies.view', 'currencies.create', 'currencies.update', 'currencies.delete', 'currencies.set_default'],
        'users' => ['users.view', 'users.create', 'users.update', 'users.delete'],
        'roles' => ['roles.view', 'roles.create', 'roles.update', 'roles.delete'],
        'settings' => ['settings.general', 'settings.contact', 'settings.store', 'settings.seo', 'settings.mail', 'settings.invoice'],
        'pos' => ['pos.access', 'pos.companies.create', 'pos.companies.update'],
    ];

    /** Roles shipped with the system. super-admin passes every check (Gate::before) and cannot be edited. */
    public const SUPER_ADMIN = 'super-admin';

    /** @return list<string> */
    public static function all(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    /** @return array<string, list<string>> */
    public static function defaultRoles(): array
    {
        $catalog = [
            ...self::GROUPS['products'], ...self::GROUPS['categories'], ...self::GROUPS['attributes'], ...self::GROUPS['brands'],
        ];

        return [
            self::SUPER_ADMIN => self::all(),
            'admin' => self::all(),
            'product-manager' => ['admin.access', ...$catalog, ...self::GROUPS['price_tiers']],
            'content-manager' => [
                'admin.access', 'products.view', 'products.update',
                ...self::GROUPS['categories'], ...self::GROUPS['brands'],
                'settings.general', 'settings.contact', 'settings.seo', 'analytics.view',
            ],
            'order-manager' => [
                'admin.access', 'orders.view', 'orders.update_status', 'products.view', 'customers.view', 'traders.view',
                'companies.view', 'companies.create', 'companies.update', 'companies.payments.create',
            ],
            'customer' => [], // storefront account only — no admin access
            // iPad POS only: places on-account orders for companies; no admin panel.
            'sales-rep' => ['pos.access', 'pos.companies.create', 'pos.companies.update'],
        ];
    }

    /** Built-in roles cannot be deleted or renamed (code and tests rely on their names). */
    public static function isBuiltIn(string $role): bool
    {
        return array_key_exists($role, self::defaultRoles());
    }
}
