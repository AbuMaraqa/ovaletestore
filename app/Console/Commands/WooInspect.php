<?php

namespace App\Console\Commands;

use App\Migration\Woo\BilingualName;
use App\Migration\Woo\StoreApiClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('woo:inspect')]
#[Description('Read-only data-quality report of the WooCommerce source (no writes)')]
class WooInspect extends Command
{
    public function handle(): int
    {
        $client = StoreApiClient::fromConfig();
        $totals = $client->productTotals();
        $categories = $client->categories();

        $stats = ['types' => [], 'images' => 0, 'unique_images' => [], 'no_sku' => 0, 'skus' => [], 'no_en' => 0, 'on_sale' => 0, 'uncategorised' => 0, 'out_of_stock' => 0];

        $bar = $this->output->createProgressBar($totals['total']);
        foreach ($client->productPages() as $products) {
            foreach ($products as $p) {
                $stats['types'][$p['type']] = ($stats['types'][$p['type']] ?? 0) + 1;
                $stats['images'] += count($p['images']);
                foreach ($p['images'] as $img) {
                    $stats['unique_images'][$img['src']] = true;
                }
                $p['sku'] ? $stats['skus'][$p['sku']] = ($stats['skus'][$p['sku']] ?? 0) + 1 : $stats['no_sku']++;
                $stats['no_en'] += BilingualName::split($p['name'])['en'] === null ? 1 : 0;
                $stats['on_sale'] += $p['on_sale'] ? 1 : 0;
                $stats['uncategorised'] += empty($p['categories']) ? 1 : 0;
                $stats['out_of_stock'] += $p['is_in_stock'] ? 0 : 1;
                $bar->advance();
            }
        }
        $bar->finish();
        $this->newLine(2);

        $this->table(['Metric', 'Value'], [
            ['Products (Store API)', $totals['total']],
            ['Types', collect($stats['types'])->map(fn ($n, $t) => "{$t}: {$n}")->implode(', ')],
            ['Categories', count($categories).' (top-level: '.collect($categories)->where('parent', 0)->count().')'],
            ['Image references / unique', $stats['images'].' / '.count($stats['unique_images'])],
            ['Missing SKU', $stats['no_sku']],
            ['Duplicate SKUs', collect($stats['skus'])->filter(fn ($n) => $n > 1)->count()],
            ['Names without English part', $stats['no_en']],
            ['On sale', $stats['on_sale']],
            ['Uncategorised', $stats['uncategorised']],
            ['Out of stock', $stats['out_of_stock']],
            ['Global attributes', count($client->attributes())],
        ]);

        return self::SUCCESS;
    }
}
