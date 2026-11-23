<?php

namespace App\Migration\Woo;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\StockStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Redirect;
use App\Support\RichText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Maps Store API payloads to our schema. Idempotent: everything is keyed by source_id,
 * so re-running updates existing rows instead of duplicating them.
 */
class WooImporter
{
    /** @var array<int, int> woo category id => local id */
    private array $categoryMap = [];

    /** @var array<string, int> sku => woo product id that claimed it in this run */
    private array $claimedSkus = [];

    public function __construct(private readonly bool $dryRun = false) {}

    /** @param list<array<string, mixed>> $categories */
    public function importCategories(array $categories): int
    {
        // Parents first so parent_id can be resolved.
        usort($categories, fn ($a, $b) => ($a['parent'] <=> $b['parent']) ?: ($a['id'] <=> $b['id']));

        foreach ($categories as $index => $row) {
            $names = BilingualName::split((string) $row['name'], allowTrailingLatin: true);

            if ($this->dryRun) {
                $this->categoryMap[(int) $row['id']] = 0;

                continue;
            }

            DB::transaction(function () use ($row, $names, $index): void {
                $category = Category::query()->firstOrNew(['source_id' => (int) $row['id']]);
                $category->fill([
                    'parent_id' => $row['parent'] ? ($this->categoryMap[(int) $row['parent']] ?? null) : null,
                    'sort_order' => $category->exists ? $category->sort_order : $index,
                    'is_active' => true,
                ]);

                $ar = $category->translateOrNew('ar');
                $ar->name = $names['ar'];
                $ar->slug = rawurldecode((string) $row['slug']);
                $ar->description = RichText::clean($row['description'] ?? null);

                if ($names['en']) {
                    $en = $category->translateOrNew('en');
                    $en->name = $names['en'];
                    $en->slug ??= $names['en'];
                }

                $category->save();
                $this->categoryMap[(int) $row['id']] = $category->id;

                if ($names['en']) {
                    $this->redirect('/en/product-category/'.$row['slug'], '/en/product-category/'.$category->translate('en', false)?->slug);
                }
            });
        }

        return count($categories);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{product: ?Product, warnings: list<string>}
     */
    public function importProduct(array $row): array
    {
        $warnings = [];
        $names = BilingualName::split((string) $row['name'], allowTrailingLatin: true, minTrailingWords: 2);
        $sku = $this->resolveSku($row, $warnings);
        $prices = $this->prices($row);

        if ($names['en'] === null) {
            $warnings[] = 'no English name — en falls back to ar';
        }

        if ($this->dryRun) {
            return ['product' => null, 'warnings' => $warnings];
        }

        $product = DB::transaction(function () use ($row, $names, $sku, $prices): Product {
            $product = Product::withTrashed()->firstOrNew(['source_id' => (int) $row['id']]);
            $product->fill([
                'type' => ProductType::Simple,
                'status' => ProductStatus::Published,
                'published_at' => $product->published_at ?? now(),
            ]);

            // The source keeps Arabic + English in one field; give each locale its own text.
            $description = BilingualHtml::split(RichText::clean($row['description'] ?? null));
            $short = BilingualHtml::split(RichText::clean($row['short_description'] ?? null));

            $ar = $product->translateOrNew('ar');
            $ar->name = $names['ar'];
            $ar->slug = rawurldecode((string) $row['slug']);
            $ar->setAttribute('short_description', RichText::clean($short['ar']));
            $ar->setAttribute('description', RichText::clean($description['ar']));

            // English name: from the source, otherwise an existing (manually/AI translated) one is kept.
            $enName = $names['en'] ?? ($product->hasTranslation('en') ? $product->translate('en')->getAttribute('name') : null);
            if ($enName) {
                $en = $product->translateOrNew('en');
                $en->setAttribute('name', $enName);
                $en->setAttribute('slug', $en->getAttribute('slug') ?? $enName);
                $en->setAttribute('description', RichText::clean($description['en']) ?? $en->getAttribute('description'));
                $en->setAttribute('short_description', RichText::clean($short['en']) ?? $en->getAttribute('short_description'));
            }

            $product->save();
            if ($product->trashed()) {
                $product->restore();
            }

            $categoryIds = collect($row['categories'] ?? [])
                ->map(fn ($c) => $this->categoryMap[(int) $c['id']] ?? Category::query()->where('source_id', $c['id'])->value('id'))
                ->filter()->values();
            $product->categories()->sync(
                $categoryIds->mapWithKeys(fn ($id, $i) => [$id => ['is_primary' => $i === 0]])->all(),
            );

            ProductVariant::query()->updateOrCreate(
                ['product_id' => $product->id, 'signature' => ''],
                [
                    'sku' => $sku,
                    'regular_price' => $prices['regular'],
                    'sale_price' => $prices['sale'],
                    'stock_status' => ($row['is_in_stock'] ?? true) ? StockStatus::InStock : StockStatus::OutOfStock,
                    'manage_stock' => false,
                    'is_active' => true,
                    'is_default' => true,
                ],
            );

            $this->registerLegacyRedirects($product, (string) $row['slug']);

            return $product;
        });

        return ['product' => $product, 'warnings' => $warnings];
    }

    /** @return array{regular:int, sale:?int} minor units */
    private function prices(array $row): array
    {
        $p = $row['prices'] ?? [];
        $factor = 10 ** (2 - (int) ($p['currency_minor_unit'] ?? 2));
        $regular = (int) ($p['regular_price'] ?? $p['price'] ?? 0) * $factor;
        $sale = (int) ($p['sale_price'] ?? 0) * $factor;

        return [
            'regular' => $regular,
            'sale' => ($row['on_sale'] ?? false) && $sale > 0 && $sale < $regular ? $sale : null,
        ];
    }

    /** SKUs are unique here; duplicates in the source keep the first owner, later ones get null. */
    private function resolveSku(array $row, array &$warnings): ?string
    {
        $sku = trim((string) ($row['sku'] ?? '')) ?: null;
        if ($sku === null) {
            $warnings[] = 'missing SKU';

            return null;
        }

        $owner = $this->claimedSkus[$sku] ?? null;
        $dbOwner = ProductVariant::query()->where('sku', $sku)
            ->whereHas('product', fn ($q) => $q->withTrashed()->where('source_id', '!=', $row['id']))
            ->exists();

        if (($owner !== null && $owner !== (int) $row['id']) || $dbOwner) {
            $warnings[] = "duplicate SKU {$sku} — cleared";

            return null;
        }

        $this->claimedSkus[$sku] = (int) $row['id'];

        return $sku;
    }

    /** TranslatePress served English at /en/product/{arabic-slug}; our English slug differs. */
    private function registerLegacyRedirects(Product $product, string $sourceSlug): void
    {
        $enSlug = $product->translate('en', false)?->slug;
        if (! $enSlug) {
            return;
        }

        $this->redirect('/en/product/'.$sourceSlug, '/en/product/'.$enSlug);
    }

    private function redirect(string $fromUrlPath, string $to): void
    {
        $from = Redirect::normalizePath($fromUrlPath);

        if ($from !== Redirect::normalizePath($to)) {
            Redirect::query()->updateOrCreate(['from_path' => $from], ['to_path' => $to, 'status_code' => 301]);
        }
    }

    public function logWarning(int $sourceId, string $message): void
    {
        Log::channel('woo')->warning("[woo #{$sourceId}] {$message}");
    }
}
