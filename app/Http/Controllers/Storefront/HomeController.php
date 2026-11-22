<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\CategoryResource;
use App\Http\Resources\Storefront\ProductCardResource;
use App\Models\Category;
use App\Models\Product;
use App\Settings\SeoSettings;
use App\Support\Seo\JsonLd;
use App\Support\Seo\SeoData;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(SeoSettings $seo): Response
    {
        $locale = app()->getLocale();
        $cardRelations = ['translations', 'activeVariants', 'media'];

        return Inertia::render('Storefront/Home/Index', [
            'categories' => CategoryResource::collection(
                Category::query()->active()->whereNull('parent_id')->ordered()
                    ->with(['translations', 'media'])
                    ->withCount(['products' => fn ($q) => $q->published()])
                    ->get()
                    ->filter(fn (Category $c) => $c->products_count > 0)
                    ->values(),
            ),
            'featured' => ProductCardResource::collection(
                Product::query()->published()->where('is_featured', true)->with($cardRelations)->latest('published_at')->limit(8)->get(),
            ),
            'newArrivals' => ProductCardResource::collection(
                Product::query()->published()->with($cardRelations)->latest('published_at')->latest('id')->limit(8)->get(),
            ),
            'seo' => SeoData::make($seo->home_title[$locale] ?? $seo->home_title['ar'], $seo->home_description[$locale] ?? null)
                ->forCurrentRoute()
                ->withJsonLd(JsonLd::organization())
                ->withJsonLd(JsonLd::website()),
        ]);
    }
}
