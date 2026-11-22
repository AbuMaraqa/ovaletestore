<?php

use App\Actions\Products\GenerateVariantCombinations;
use App\Actions\Products\SaveProduct;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

function variableProductPayload(array $attributes, array $variants): array
{
    return [
        'type' => 'variable',
        'status' => 'published',
        'translations' => ['ar' => ['name' => 'شوكولاتة'], 'en' => ['name' => 'Chocolate']],
        'attributes' => $attributes,
        'variants' => $variants,
    ];
}

it('creates a simple product with exactly one default variant', function () {
    $product = makeSimpleProduct(price: '12.50', variant: ['sku' => 'SKU-1']);

    expect($product->variants)->toHaveCount(1)
        ->and($product->variants->first()->signature)->toBe('')
        ->and($product->variants->first()->is_default)->toBeTrue()
        ->and($product->variants->first()->regular_price)->toBe(1250);
});

it('generates every combination of the selected terms', function () {
    $combos = app(GenerateVariantCombinations::class)->handle([1 => [10, 11], 2 => [20, 21, 22]]);

    expect($combos)->toHaveCount(6)->and($combos[0])->toBe([10, 20]);
});

it('creates a variable product with relational variants, prices and stock per combination', function () {
    $weight = makeAttribute('weight', ['1 كغ', '5 كغ']);
    $pack = makeAttribute('packaging', ['كيس', 'علبة']);
    [$kg1, $kg5] = $weight->terms->pluck('id')->all();
    [$bag, $box] = $pack->terms->pluck('id')->all();

    $product = app(SaveProduct::class)->handle(variableProductPayload(
        [
            ['attribute_id' => $weight->id, 'term_ids' => [$kg1, $kg5], 'used_for_variations' => true],
            ['attribute_id' => $pack->id, 'term_ids' => [$bag, $box], 'used_for_variations' => true],
        ],
        [
            ['term_ids' => [$kg1, $bag], 'regular_price' => 3000, 'stock_status' => 'in_stock', 'sku' => 'CH-1-BAG'],
            ['term_ids' => [$kg1, $box], 'regular_price' => 3500, 'stock_status' => 'in_stock'],
            ['term_ids' => [$kg5, $bag], 'regular_price' => 12000, 'sale_price' => 11000, 'stock_status' => 'in_stock', 'is_default' => true],
            ['term_ids' => [$kg5, $box], 'regular_price' => 13000, 'manage_stock' => true, 'stock_quantity' => 3, 'stock_status' => 'in_stock'],
        ],
    ));

    expect($product->variants)->toHaveCount(4);

    $default = $product->variants->firstWhere('is_default', true);
    expect($default->effectivePrice())->toBe(11000)
        ->and($default->terms->pluck('id')->sort()->values()->all())->toBe(collect([$kg5, $bag])->sort()->values()->all());

    $limited = ProductVariant::query()->where('signature', ProductVariant::signatureFor([$kg5, $box]))->first();
    expect($limited->availableQuantity())->toBe(3);

    // No JSON storage: combinations live in the pivot table.
    expect(DB::table('product_variant_term')->count())->toBe(8);
});

it('rejects a variation that skips an attribute or uses foreign terms', function () {
    $weight = makeAttribute('weight', ['1 كغ', '5 كغ']);
    $pack = makeAttribute('packaging', ['كيس']);
    $foreign = makeAttribute('color', ['أحمر']);

    app(SaveProduct::class)->handle(variableProductPayload(
        [
            ['attribute_id' => $weight->id, 'term_ids' => $weight->terms->pluck('id')->all(), 'used_for_variations' => true],
            ['attribute_id' => $pack->id, 'term_ids' => $pack->terms->pluck('id')->all(), 'used_for_variations' => true],
        ],
        [['term_ids' => [$weight->terms[0]->id, $foreign->terms[0]->id], 'regular_price' => 1000, 'stock_status' => 'in_stock']],
    ));
})->throws(ValidationException::class);

it('rejects duplicate combinations', function () {
    $weight = makeAttribute('weight', ['1 كغ']);
    $termId = $weight->terms[0]->id;

    app(SaveProduct::class)->handle(variableProductPayload(
        [['attribute_id' => $weight->id, 'term_ids' => [$termId], 'used_for_variations' => true]],
        [
            ['term_ids' => [$termId], 'regular_price' => 1000, 'stock_status' => 'in_stock'],
            ['term_ids' => [$termId], 'regular_price' => 2000, 'stock_status' => 'in_stock'],
        ],
    ));
})->throws(ValidationException::class);

it('keeps variant ids stable when re-saving and removes dropped combinations', function () {
    $weight = makeAttribute('weight', ['1 كغ', '5 كغ']);
    [$a, $b] = $weight->terms->pluck('id')->all();
    $attributes = [['attribute_id' => $weight->id, 'term_ids' => [$a, $b], 'used_for_variations' => true]];

    $product = app(SaveProduct::class)->handle(variableProductPayload($attributes, [
        ['term_ids' => [$a], 'regular_price' => 1000, 'stock_status' => 'in_stock'],
        ['term_ids' => [$b], 'regular_price' => 2000, 'stock_status' => 'in_stock'],
    ]));
    $keptId = $product->variants->firstWhere('signature', (string) $a)->id;

    $product = app(SaveProduct::class)->handle(variableProductPayload($attributes, [
        ['term_ids' => [$a], 'regular_price' => 1500, 'stock_status' => 'in_stock'],
    ]), $product);

    expect($product->variants)->toHaveCount(1)
        ->and($product->variants->first()->id)->toBe($keptId)
        ->and($product->variants->first()->regular_price)->toBe(1500)
        ->and($product->variants->first()->is_default)->toBeTrue();
});
