<?php

namespace App\Tables;

/** Every admin table, by key (used by the shared export endpoint). */
final class Tables
{
    /** @var list<class-string<Table<*>>> */
    public const ALL = [
        Admin\ProductsTable::class,
        Admin\CategoriesTable::class,
        Admin\AttributesTable::class,
        Admin\BrandsTable::class,
        Admin\OrdersTable::class,
        Admin\CustomersTable::class,
        Admin\TradersTable::class,
        Admin\PriceTiersTable::class,
        Admin\CompaniesTable::class,
        Admin\ShippingMethodsTable::class,
        Admin\CurrenciesTable::class,
        Admin\UsersTable::class,
        Admin\RolesTable::class,
        Admin\ActivityTable::class,
    ];

    /** @return class-string<Table<*>>|null */
    public static function find(string $key): ?string
    {
        foreach (self::ALL as $class) {
            if ($class::key() === $key) {
                return $class;
            }
        }

        return null;
    }
}
