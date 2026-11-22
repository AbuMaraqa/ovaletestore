<?php

use App\Jobs\ImportProductImage;
use App\Migration\Woo\WooImporter;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Redirect;

function wooProduct(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 501,
        'name' => 'كريما طبيخ مستر شيف 500 مل.Mr. Chef Cooking Cream – 500 ml',
        'slug' => rawurlencode('كريما-طبيخ-مستر-شيف'),
        'sku' => 'MC-500',
        'description' => '<p>كريمة</p><script>x</script>',
        'short_description' => '',
        'on_sale' => false,
        'is_in_stock' => true,
        'prices' => ['regular_price' => '1850', 'sale_price' => '1850', 'price' => '1850', 'currency_minor_unit' => 2],
        'categories' => [['id' => 54]],
        'images' => [],
    ], $overrides);
}

it('imports categories and products idempotently', function () {
    $importer = new WooImporter;
    $importer->importCategories([['id' => 54, 'parent' => 0, 'name' => 'كريما و ألبان.Creams&amp;Dairy', 'slug' => rawurlencode('كريما-creams'), 'description' => '']]);

    $importer->importProduct(wooProduct());
    $importer->importProduct(wooProduct(['prices' => ['regular_price' => '1900']]));

    $product = Product::query()->with(['translations', 'defaultVariant', 'categories'])->sole();

    expect(Product::query()->count())->toBe(1)
        ->and(ProductVariant::query()->count())->toBe(1)
        ->and($product->translate('ar')->name)->toBe('كريما طبيخ مستر شيف 500 مل')
        ->and($product->translate('en')->name)->toBe('Mr. Chef Cooking Cream – 500 ml')
        ->and($product->translate('ar')->slug)->toBe('كريما-طبيخ-مستر-شيف')
        ->and($product->translate('ar')->description)->toBe('<p>كريمة</p>')
        ->and($product->defaultVariant->regular_price)->toBe(1900)
        ->and($product->categories->first()->translate('en')->name)->toBe('Creams&Dairy')
        ->and(Category::query()->count())->toBe(1);
});

it('clears duplicate SKUs instead of failing', function () {
    $importer = new WooImporter;
    $importer->importProduct(wooProduct(['id' => 1, 'slug' => 'a']));
    $result = $importer->importProduct(wooProduct(['id' => 2, 'slug' => 'b']));

    expect($result['warnings'])->toContain('duplicate SKU MC-500 — cleared')
        ->and(ProductVariant::query()->whereNotNull('sku')->count())->toBe(1);
});

it('registers a 301 for the old TranslatePress English URL', function () {
    (new WooImporter)->importProduct(wooProduct());

    $redirect = Redirect::query()->sole();
    expect($redirect->from_path)->toBe('/en/product/كريما-طبيخ-مستر-شيف')
        ->and($redirect->to_path)->toBe('/en/product/mr-chef-cooking-cream-500-ml');

    $this->useRouteLocale('en')->get('/en/product/'.rawurlencode('كريما-طبيخ-مستر-شيف').'/')->assertRedirect(url('/en/product/mr-chef-cooking-cream-500-ml'));
});

it('writes nothing in dry-run mode', function () {
    (new WooImporter(dryRun: true))->importProduct(wooProduct());

    expect(Product::query()->count())->toBe(0);
});

it('prefers the original over the WordPress "-scaled" image', function () {
    $candidates = ImportProductImage::originalCandidates('https://ovaletestore.com/wp-content/uploads/2025/05/cake-scaled.jpg');

    expect($candidates[0])->toEndWith('/cake.jpg')->and($candidates[1])->toEndWith('/cake-scaled.jpg');
});
