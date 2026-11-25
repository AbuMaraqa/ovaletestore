<?php

namespace App\Console\Commands;

use App\Migration\Woo\AdminApiClient;
use App\Migration\Woo\WooCustomerMapper;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Reads every WordPress user through wp-admin and writes the store customers as seed data for
 * Database\Seeders\CustomerSeeder. The file holds personal data: git-ignored, never commit or share it.
 * Passwords cannot be exported (WordPress never returns hashes): customers set a new one via "forgot password".
 */
#[Signature('woo:export-customers {--path= : Output file (default: database/data/customers/customers.json)} {--delay=1500 : Pause between pages in ms}')]
#[Description('Export WooCommerce customer accounts (read-only) as seed data for CustomerSeeder')]
class WooExportCustomers extends Command
{
    public function handle(): int
    {
        $path = $this->option('path') ?: database_path('data/customers/customers.json');
        $customers = [];
        $roles = [];

        try {
            $client = AdminApiClient::fromConfig();
            foreach ($client->customerPages() as $page) {
                foreach ($page as $row) {
                    $roles[$row['role'] ?? '?'] = ($roles[$row['role'] ?? '?'] ?? 0) + 1;
                    if ($mapped = WooCustomerMapper::map($row)) {
                        $customers[] = $mapped;
                    }
                }
                usleep(max(0, (int) $this->option('delay')) * 1000);
            }
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'exported_at' => now()->toIso8601String(),
            'source_roles' => $roles,
            'customers' => $customers,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        ksort($roles);
        $this->table(['WordPress role', 'Users'], collect($roles)->map(fn ($n, $r) => [$r, $n])->values()->all());
        $this->components->info(count($customers)." customers written to {$path} — personal data, do not commit. Next: php artisan db:seed --class=CustomerSeeder");

        return self::SUCCESS;
    }
}
