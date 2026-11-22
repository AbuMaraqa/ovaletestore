<?php

use App\Models\Order;

it('renders every homepage section', function () {
    makeSimpleProduct(name: 'شوكولاتة');
    makeSimpleProduct(name: 'كريمة', variant: ['regular_price' => 3000, 'sale_price' => 2500]);

    $this->get('/ar')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Storefront/Home/Index')
        ->has('hero.title')
        ->has('brands')
        ->has('categories')
        ->has('featured', 2)
        ->has('offers', 1)
        ->where('bestSellers.is_sales_based', false));
});

it('labels best sellers from real order data only', function () {
    $products = collect(range(1, 4))->map(fn ($i) => makeSimpleProduct(name: "منتج {$i}"));
    $order = Order::query()->create([
        'number' => 'OV-TEST', 'status' => 'completed', 'customer_name' => 'x', 'customer_phone' => '0599000000',
        'shipping_city' => 'x', 'shipping_address' => 'x', 'currency' => 'ILS', 'subtotal' => 1, 'total' => 1, 'locale' => 'ar', 'placed_at' => now(),
    ]);
    $products->each(fn ($p, $i) => $order->items()->create([
        'product_id' => $p->id, 'name' => 'x', 'unit_price' => 100, 'quantity' => 4 - $i, 'line_total' => 100,
    ]));

    $this->get('/ar')->assertInertia(fn ($page) => $page
        ->where('bestSellers.is_sales_based', true)
        ->where('bestSellers.products.0.id', $products[0]->id));
});

it('filters the catalog to products on sale', function () {
    makeSimpleProduct(name: 'عادي');
    $sale = makeSimpleProduct(name: 'مخفض', variant: ['regular_price' => 3000, 'sale_price' => 2000]);

    $this->get(route('storefront.shop', ['sale' => 1]))->assertOk()->assertInertia(fn ($page) => $page
        ->has('products.data', 1)
        ->where('products.data.0.id', $sale->id)
        ->where('filters.sale', true));
});
