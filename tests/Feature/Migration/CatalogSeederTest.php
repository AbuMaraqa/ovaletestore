<?php

use App\Jobs\ImportRemoteImage;
use App\Models\Category;
use App\Models\Product;
use App\Models\ShippingMethod;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ShippingMethodSeeder;
use Illuminate\Support\Facades\Queue;

it('seeds the full catalog with prices and translations from the snapshot, idempotently', function () {
    Queue::fake();
    $snapshot = collect(json_decode(file_get_contents(database_path('data/catalog/products.json')), true));

    $this->seed(CatalogSeeder::class);
    $this->seed(CatalogSeeder::class); // re-run: updates, no duplicates

    expect(Product::query()->count())->toBe($snapshot->count())
        ->and(Category::query()->count())->toBe(count(json_decode(file_get_contents(database_path('data/catalog/categories.json')), true)));

    $sample = $snapshot->firstWhere('source_id', 5916);
    $product = Product::query()->where('source_id', 5916)->with('translations', 'defaultVariant')->sole();

    expect($product->defaultVariant->regular_price)->toBe($sample['regular_price'])
        ->and($product->translate('ar')->getAttribute('slug'))->toBe($sample['translations']['ar']['slug'])
        ->and($product->translate('he')->getAttribute('name'))->toBe($sample['translations']['he']['name'])
        ->and($product->categories()->count())->toBe(count($sample['categories']));

    Queue::assertPushed(ImportRemoteImage::class);
});

it('seeds the delivery regions and store pickup without overwriting edited prices', function () {
    $westBank = ShippingMethod::query()->where('code', 'west_bank')->sole();
    $westBank->update(['cost' => 3000]);

    $this->seed(ShippingMethodSeeder::class);

    expect(ShippingMethod::query()->whereIn('code', ['inside', 'west_bank', 'jerusalem', 'pickup'])->count())->toBe(4)
        ->and($westBank->fresh()->cost)->toBe(3000);
});
