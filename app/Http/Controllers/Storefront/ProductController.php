<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\ProductCardResource;
use App\Http\Resources\Storefront\ProductDetailResource;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Support\Seo\JsonLd;
use App\Support\Seo\SeoData;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(string $slug): Response|RedirectResponse
    {
        $product = $this->resolve($slug);

        // One canonical URL per locale: a slug from another locale 301s to this locale's slug.
        if ($product->slug !== rawurldecode($slug)) {
            return redirect()->route('storefront.product', ['slug' => $product->slug], 301);
        }

        $product->load([
            'brand.translations',
            'categories.translations',
            'media',
            'productAttributes.attribute.translations',
            'productAttributes.terms.translations',
            'activeVariants.terms.translations',
            'activeVariants.media',
            'defaultVariant',
        ]);

        $url = route('storefront.product', ['slug' => $product->slug]);
        $image = $product->getFirstMedia('gallery');
        $imageUrl = $image ? ($image->hasGeneratedConversion('large') ? $image->getUrl('large') : $image->getUrl()) : null;
        $category = $product->categories->sortByDesc('pivot.is_primary')->first();

        $breadcrumbs = array_values(array_filter([
            ['name' => __('common.home'), 'url' => route('storefront.home')],
            $category ? ['name' => $category->name, 'url' => route('storefront.category', ['slug' => $category->slug])] : null,
            ['name' => $product->name, 'url' => $url],
        ]));

        return Inertia::render('Storefront/Products/Show', [
            'product' => new ProductDetailResource($product),
            'breadcrumbs' => $breadcrumbs,
            'related' => Inertia::defer(fn () => ProductCardResource::collection($this->related($product))),
            // Title/description/image/hreflang from the product (HasSeoPage) + its `seo` row overrides.
            'seo' => SeoData::for($product)
                ->breadcrumbs($breadcrumbs)
                ->withJsonLd(JsonLd::product($product, $url, $imageUrl)),
        ]);
    }

    private function resolve(string $slug): Product
    {
        $translation = ProductTranslation::query()
            ->where('slug', rawurldecode($slug))
            ->orderByRaw('locale = ? desc', [app()->getLocale()])
            ->firstOrFail();

        return Product::query()->published()->with('translations')->findOrFail($translation->product_id);
    }

    private function related(Product $product)
    {
        $categoryIds = $product->categories->pluck('id');

        return Product::query()->published()
            ->whereKeyNot($product->id)
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
            ->with(['translations', 'activeVariants', 'media', 'categories.translations'])
            ->inRandomOrder()
            ->limit(8)
            ->get();
    }
}
