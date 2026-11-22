<?php

namespace App\Console\Commands;

use App\Jobs\ImportProductImage;
use App\Migration\Woo\StoreApiClient;
use App\Migration\Woo\WooImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

#[Signature('woo:migrate
    {--dry-run : Map everything and report, write nothing}
    {--images : Import product images (queued unless --sync-images)}
    {--sync-images : Download/attach images inline instead of queueing}
    {--fresh : Ignore the checkpoint and start from page 1}')]
#[Description('Import categories, products, prices and images from the WooCommerce source (idempotent)')]
class WooMigrate extends Command
{
    private const CHECKPOINT = 'woo:migrate:checkpoint';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $client = StoreApiClient::fromConfig();
        $importer = new WooImporter($dryRun);

        if ($this->option('fresh')) {
            Cache::forget(self::CHECKPOINT);
        }

        $this->components->task('Categories', fn () => $importer->importCategories($client->categories()) >= 0);

        $totals = $client->productTotals();
        $startPage = $dryRun ? 1 : (int) Cache::get(self::CHECKPOINT, 0) + 1;
        if ($startPage > 1) {
            $this->components->info("Resuming from source page {$startPage} (use --fresh to restart).");
        }

        $counts = ['imported' => 0, 'warnings' => 0, 'errors' => 0, 'images' => 0];
        $bar = $this->output->createProgressBar($totals['total']);
        $bar->advance(($startPage - 1) * (int) config('services.woocommerce.per_page'));

        foreach ($client->productPages($startPage) as $page => $products) {
            foreach ($products as $row) {
                try {
                    $result = $importer->importProduct($row);
                    $counts['imported']++;

                    foreach ($result['warnings'] as $warning) {
                        $counts['warnings']++;
                        $importer->logWarning((int) $row['id'], $warning);
                    }

                    if ($result['product'] && $this->option('images')) {
                        $counts['images'] += $this->dispatchImages($result['product']->id, $row);
                    }
                } catch (Throwable $e) {
                    $counts['errors']++;
                    $importer->logWarning((int) $row['id'], 'ERROR '.$e->getMessage());
                }
                $bar->advance();
            }

            if (! $dryRun) {
                Cache::forever(self::CHECKPOINT, $page);
            }
        }

        $bar->finish();
        $this->newLine(2);

        if (! $dryRun) {
            Cache::forget(self::CHECKPOINT); // completed run
        }

        $this->table(['Result', 'Count'], collect($counts)->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->components->info(($dryRun ? '[dry-run] nothing written. ' : '').'Details: storage/logs/woo-migration.log');

        return $counts['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function dispatchImages(int $productId, array $row): int
    {
        foreach (array_values($row['images'] ?? []) as $position => $image) {
            $job = new ImportProductImage($productId, (string) $image['src'], $position, $image['alt'] ?: null);
            $this->option('sync-images') ? dispatch_sync($job) : dispatch($job);
        }

        return count($row['images'] ?? []);
    }
}
