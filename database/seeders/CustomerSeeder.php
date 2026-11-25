<?php

namespace Database\Seeders;

use App\Enums\TraderStatus;
use App\Models\PriceTier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Loads the WooCommerce customer accounts written by `php artisan woo:export-customers`
 * (database/data/customers/customers.json — git-ignored, it holds personal data).
 *
 * - Idempotent: matched by `source_id`, then by email (an account the customer already opened here
 *   is linked, never duplicated). Existing accounts keep their password, roles and status.
 * - New accounts get an unguessable random password: the customer sets their own through
 *   "forgot password" (WordPress never exposes password hashes).
 * - Must run before OrderSeeder, which links orders to accounts by the WooCommerce customer id.
 */
class CustomerSeeder extends Seeder
{
    public function run(?string $path = null): void
    {
        $path ??= database_path('data/customers/customers.json');
        if (! File::exists($path)) {
            $this->command?->warn("CustomerSeeder skipped: {$path} not found (run php artisan woo:export-customers).");

            return;
        }

        $rows = (array) (json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR)['customers'] ?? []);
        $counts = ['created' => 0, 'linked' => 0];

        $bySource = User::query()->whereIn('source_id', array_column($rows, 'source_id'))->get()->keyBy('source_id');
        $byEmail = User::query()->with('roles')->get(['id', 'email', 'email_verified_at', 'source_id', 'phone'])
            ->keyBy(fn (User $u) => strtolower($u->email));
        $counts['skipped'] = 0;
        $counts['traders'] = 0;
        $wholesaleTier = PriceTier::query()->where('code', 'wholesale')->value('id');

        Model::withoutEvents(function () use ($rows, $bySource, $byEmail, $wholesaleTier, &$counts) {
            foreach ($rows as $row) {
                if ($existing = $bySource->get($row['source_id'])) {
                    // Re-runs: a WooCommerce wholesale customer imported before trader tiers existed.
                    if (($row['woo_role'] ?? null) === 'wholesale_customer' && $wholesaleTier && $existing->trader_status === null) {
                        $existing->forceFill(['trader_status' => TraderStatus::Approved, 'price_tier_id' => $wholesaleTier, 'trader_requested_at' => $existing->created_at])->saveQuietly();
                        $counts['traders']++;
                    }
                    $counts['linked']++;

                    continue;
                }

                if ($existing = $byEmail->get($row['email'])) {
                    // Link old order history only to an account that proved it owns the address and is a
                    // plain customer — never to an unverified sign-up or a staff account.
                    if ($existing->email_verified_at && $existing->source_id === null && $existing->roles->pluck('name')->diff(['customer'])->isEmpty()) {
                        $existing->forceFill(['source_id' => $row['source_id'], 'phone' => $existing->phone ?: $row['phone']])->saveQuietly();
                        if (! $existing->hasRole('customer')) {
                            $existing->assignRole('customer');
                        }
                        $counts['linked']++;
                    } else {
                        $this->command?->warn("Skipped {$row['email']}: an unverified or staff account already uses it.");
                        $counts['skipped']++;
                    }

                    continue;
                }

                $created = $row['created_at'] ? Carbon::parse($row['created_at'], 'UTC') : now();
                $user = new User;
                $user->timestamps = false;
                $user->forceFill([
                    'source_id' => $row['source_id'],
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                    'password' => Str::password(40),
                    'is_active' => true,
                    'email_verified_at' => $created, // the address was in use on the old store
                    'created_at' => $created,
                    'updated_at' => $created,
                ])->save();
                $user->assignRole('customer');
                // WooCommerce "Wholesale Prices" customers keep buying at wholesale here.
                if (($row['woo_role'] ?? null) === 'wholesale_customer' && $wholesaleTier) {
                    $user->forceFill(['trader_status' => TraderStatus::Approved, 'price_tier_id' => $wholesaleTier, 'trader_requested_at' => $created])->save();
                    $counts['traders']++;
                }
                $counts['created']++;
            }
        });

        $this->command?->info("Customers: {$counts['created']} created ({$counts['traders']} wholesale traders), {$counts['linked']} linked, {$counts['skipped']} skipped.");
    }
}
