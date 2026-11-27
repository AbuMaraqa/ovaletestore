<?php

namespace App\Support;

use App\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

/**
 * Registry of what the audit trail records: URL-safe subject keys, their log (channel) and the
 * admin page that shows the record. Only types listed here can be browsed through the activity API.
 */
final class ActivityLog
{
    /** @var array<string, array{model: class-string, log: string, route?: string}> */
    public const SUBJECTS = [
        'product' => ['model' => Models\Product::class, 'log' => 'catalog', 'route' => 'admin.products.edit'],
        'variant' => ['model' => Models\ProductVariant::class, 'log' => 'catalog'],
        'product_translation' => ['model' => Models\ProductTranslation::class, 'log' => 'catalog'],
        'category' => ['model' => Models\Category::class, 'log' => 'catalog', 'route' => 'admin.categories.edit'],
        'category_translation' => ['model' => Models\CategoryTranslation::class, 'log' => 'catalog'],
        'brand' => ['model' => Models\Brand::class, 'log' => 'catalog', 'route' => 'admin.brands.edit'],
        'brand_translation' => ['model' => Models\BrandTranslation::class, 'log' => 'catalog'],
        'attribute' => ['model' => Models\Attribute::class, 'log' => 'catalog', 'route' => 'admin.attributes.edit'],
        'attribute_translation' => ['model' => Models\AttributeTranslation::class, 'log' => 'catalog'],
        'attribute_term' => ['model' => Models\AttributeTerm::class, 'log' => 'catalog'],
        'attribute_term_translation' => ['model' => Models\AttributeTermTranslation::class, 'log' => 'catalog'],
        'order' => ['model' => Models\Order::class, 'log' => 'orders', 'route' => 'admin.orders.show'],
        'order_note' => ['model' => Models\OrderNote::class, 'log' => 'orders'],
        'company' => ['model' => Models\Company::class, 'log' => 'companies', 'route' => 'admin.companies.show'],
        'company_payment' => ['model' => Models\CompanyPayment::class, 'log' => 'companies'],
        'shipping_method' => ['model' => Models\ShippingMethod::class, 'log' => 'settings', 'route' => 'admin.shipping-methods.edit'],
        'shipping_method_translation' => ['model' => Models\ShippingMethodTranslation::class, 'log' => 'settings'],
        'redirect' => ['model' => Models\Redirect::class, 'log' => 'settings'],
        'price_tier' => ['model' => Models\PriceTier::class, 'log' => 'catalog', 'route' => 'admin.price-tiers.edit'],
        'price_tier_price' => ['model' => Models\PriceTierPrice::class, 'log' => 'catalog'],
        'currency' => ['model' => Models\Currency::class, 'log' => 'settings', 'route' => 'admin.currencies.edit'],
        'user' => ['model' => User::class, 'log' => 'users', 'route' => 'admin.users.edit'],
        'role' => ['model' => Role::class, 'log' => 'users', 'route' => 'admin.roles.edit'],
    ];

    /** Channels shown as a filter in the activity screen. */
    public const LOGS = ['catalog', 'orders', 'companies', 'users', 'settings', 'auth'];

    public static function logNameFor(Model $model): string
    {
        foreach (self::SUBJECTS as $subject) {
            if ($model instanceof $subject['model']) {
                return $subject['log'];
            }
        }

        return 'default';
    }

    public static function keyFor(?string $class): ?string
    {
        foreach (self::SUBJECTS as $key => $subject) {
            if ($subject['model'] === $class) {
                return $key;
            }
        }

        return null;
    }

    /** @return class-string|null */
    public static function modelFor(string $key): ?string
    {
        return self::SUBJECTS[$key]['model'] ?? null;
    }

    /** A record's own entries plus those of its children (variants, translations, notes…). */
    public static function forRecord(Builder $query, string $class, int|string $id): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $own) => $own->where('subject_type', $class)->where('subject_id', $id))
            ->orWhere(fn (Builder $child) => $child->where('parent_type', $class)->where('parent_id', $id)));
    }

    /**
     * Channels a staff member may read: an entry can hold customer details (orders), staff emails
     * and sign-in IPs (users/auth), so `activity.view` alone only opens what the person can already see.
     *
     * @return list<string>
     */
    public static function visibleLogs(User $user): array
    {
        $rules = [
            'catalog' => ['products.view', 'categories.view', 'brands.view', 'attributes.view', 'price_tiers.view'],
            'orders' => ['orders.view'],
            'companies' => ['companies.view'],
            'users' => ['users.view'], // also holds customer account changes (personal data)
            'auth' => ['users.view'],
            'settings' => ['settings.general', 'settings.contact', 'settings.store', 'settings.seo', 'settings.mail', 'settings.invoice', 'shipping.view', 'currencies.view'],
        ];

        return array_keys(array_filter($rules, fn (array $any) => collect($any)->contains(fn (string $p) => $user->can($p))));
    }

    /** Admin URL of the record an entry is about (the parent for child rows), if it still exists. */
    public static function urlFor(Activity $activity): ?string
    {
        $class = $activity->getAttribute('parent_type') ?? $activity->subject_type;
        $id = $activity->getAttribute('parent_id') ?? $activity->subject_id;
        $route = self::SUBJECTS[self::keyFor($class) ?? '']['route'] ?? null;

        if (! $route || ! $id || ! $class::query()->whereKey($id)->exists()) {
            return null;
        }

        return route($route, $id);
    }

    /** Settings are not models: record the changed keys of a settings group by hand (secrets masked). */
    public static function settingsChanged(string $group, array $before, array $after): void
    {
        $old = $new = [];
        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) != $value) {
                $secret = preg_match('/password|secret|token|key/i', (string) $key) === 1;
                $old[$key] = $secret ? '••••' : ($before[$key] ?? null);
                $new[$key] = $secret ? '••••' : $value;
            }
        }

        if ($new === []) {
            return;
        }

        activity('settings')
            ->event('updated')
            ->withChanges(['attributes' => $new, 'old' => $old])
            ->withProperties(['label' => __("admin.{$group}"), 'settings_group' => $group])
            ->log('settings_updated');
    }
}
