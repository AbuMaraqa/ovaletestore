<?php

namespace App\Migration\Woo;

/**
 * WooCommerce customer (wc/v3/customers, role=all) → seed row for Database\Seeders\CustomerSeeder.
 * Only what an account needs is kept (no addresses: every order already snapshots its own).
 * WordPress administrators are not customers and are skipped; staff accounts are created by hand.
 */
final class WooCustomerMapper
{
    /** WordPress roles that are store customers. */
    public const CUSTOMER_ROLES = ['customer', 'wholesale_customer', 'subscriber'];

    /** @param array<string, mixed> $customer */
    public static function map(array $customer): ?array
    {
        $email = strtolower(trim((string) ($customer['email'] ?? '')));
        if (! in_array($customer['role'] ?? null, self::CUSTOMER_ROLES, true) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $billing = (array) ($customer['billing'] ?? []);
        $name = trim(($customer['first_name'] ?? '').' '.($customer['last_name'] ?? ''))
            ?: trim(($billing['first_name'] ?? '').' '.($billing['last_name'] ?? ''))
            ?: trim((string) ($customer['username'] ?? ''))
            ?: strstr($email, '@', true);

        $phone = preg_replace('/[^0-9+]/', '', (string) ($billing['phone'] ?? '')) ?: null;

        return [
            'source_id' => (int) $customer['id'],
            'name' => mb_substr($name, 0, 120),
            'email' => $email,
            'phone' => $phone ? mb_substr($phone, 0, 32) : null,
            'woo_role' => $customer['role'],
            'is_paying_customer' => (bool) ($customer['is_paying_customer'] ?? false),
            'created_at' => $customer['date_created_gmt'] ?? $customer['date_created'] ?? null,
        ];
    }
}
