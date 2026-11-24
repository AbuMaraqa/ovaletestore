<?php

namespace App\Console\Commands;

use App\Migration\Woo\AdminApiClient;
use App\Migration\Woo\WooOrderMapper;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Reads every WooCommerce order (any status) + its notes through wp-admin and writes them as seed data
 * for Database\Seeders\OrderSeeder. The file holds customer personal data: it is git-ignored and must
 * never be committed or shared.
 */
#[Signature('woo:export-orders {--path= : Output file (default: database/data/orders/orders.json)} {--no-notes : Skip the order notes (one request per order)} {--delay=2000 : Pause between notes requests in ms (the source firewall blocks bursts)}')]
#[Description('Export all WooCommerce orders (read-only) as seed data for OrderSeeder')]
class WooExportOrders extends Command
{
    public function handle(): int
    {
        $path = $this->option('path') ?: database_path('data/orders/orders.json');

        try {
            $client = AdminApiClient::fromConfig();
            $client->login();
            $totals = $client->orderTotals();
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar($totals['total']);
        $orders = [];

        foreach ($client->orderPages() as $page) {
            foreach ($page as $row) {
                $notes = $this->option('no-notes') ? [] : $this->notes($client, (int) $row['id']);
                $orders[] = WooOrderMapper::map($row, $notes);
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'exported_at' => now()->toIso8601String(),
            'source_total' => $totals['total'],
            'orders' => $orders,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $this->table(['Source orders (X-WP-Total)', 'Exported', 'Order total (₪)'], [[
            $totals['total'], count($orders), number_format(array_sum(array_column($orders, 'total')) / 100, 2),
        ]]);
        $this->components->info("Wrote {$path} — contains customer data, do not commit. Next: php artisan db:seed --class=OrderSeeder");

        return count($orders) === $totals['total'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Notes are one request per order: paced, and after a dropped connection (firewall rate limit)
     * wait and try once more. A second failure aborts the run before anything is written.
     *
     * @return list<array<string, mixed>>
     */
    private function notes(AdminApiClient $client, int $orderId): array
    {
        usleep(max(0, (int) $this->option('delay')) * 1000);

        try {
            return $client->orderNotes($orderId);
        } catch (ConnectionException) {
            $this->newLine();
            $this->components->warn("Connection dropped at order {$orderId}; waiting 90s before retrying…");
            sleep(90);

            return $client->orderNotes($orderId);
        }
    }
}
