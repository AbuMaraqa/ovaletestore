<?php

use App\Models\Category;
use Inertia\Testing\AssertableInertia as Assert;

it('lists categories that have products, with product and live-offer counts', function () {
    $chocolate = Category::query()->create(['is_active' => true, 'sort_order' => 1, 'ar' => ['name' => 'شوكولاتة']]);
    $syrups = Category::query()->create(['is_active' => true, 'sort_order' => 2, 'ar' => ['name' => 'سيروبات']]);
    Category::query()->create(['is_active' => true, 'sort_order' => 3, 'ar' => ['name' => 'قسم فارغ']]);
    $hidden = Category::query()->create(['is_active' => false, 'sort_order' => 4, 'ar' => ['name' => 'قسم مخفي']]);

    makeSimpleProduct('شوكولاتة داكنة', '50.00', ['sale_price' => 4000], ['category_ids' => [$chocolate->id]]);
    makeSimpleProduct('شوكولاتة بيضاء', '30.00', [], ['category_ids' => [$chocolate->id]]);
    makeSimpleProduct('عرض منتهي', '30.00', ['sale_price' => 2000, 'sale_ends_at' => now()->subDay()->toDateTimeString()], ['category_ids' => [$syrups->id]]);
    makeSimpleProduct('مسودة', '30.00', ['sale_price' => 2000], ['status' => 'draft', 'category_ids' => [$syrups->id]]);
    makeSimpleProduct('في قسم مخفي', '30.00', [], ['category_ids' => [$hidden->id]]);

    $this->get(route('storefront.categories'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/Categories/Index')
            ->has('categories', 2)
            ->where('categories.0.name', 'شوكولاتة')
            ->where('categories.0.products_count', 2)
            ->where('categories.0.on_sale_count', 1)
            ->where('categories.0.sale_url', route('storefront.category', ['slug' => $chocolate->slug, 'sale' => 1]))
            ->where('categories.1.name', 'سيروبات')
            ->where('categories.1.products_count', 1) // the draft does not count
            ->where('categories.1.on_sale_count', 0)  // expired sale is not an offer
            ->where('seo', fn ($tags) => str_contains(seoTag($tags, 'title'), __('storefront.categories_title'))));
});

it('is in every locale and in the pages sitemap', function () {
    foreach (['ar', 'en', 'he'] as $locale) {
        $this->useRouteLocale($locale);
        $this->get(route('storefront.categories'))->assertOk();
    }

    expect($this->get('/sitemaps/ar-pages.xml')->getContent())->toContain('/ar/categories');
});
