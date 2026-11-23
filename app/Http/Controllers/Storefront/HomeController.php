<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\CategoryResource;
use App\Http\Resources\Storefront\ProductCardResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Settings\HomeSettings;
use App\Settings\SeoSettings;
use App\Support\Seo\JsonLd;
use App\Support\Seo\SeoData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Homepage sections mirror the original store (hero, USPs, brands, categories, featured,
 * coffee spotlight, best sellers, offers) with richer content and motion.
 */
class HomeController extends Controller
{
    private const CARD_RELATIONS = ['translations', 'activeVariants', 'media', 'categories.translations'];

    public function __invoke(SeoSettings $seo, HomeSettings $home): Response
    {
        $locale = app()->getLocale();
        $featured = $this->featured();
        $bestSellers = $this->bestSellers($featured->modelKeys());
        $spotlight = $home->spotlight_category_id
            ? Category::query()->active()->with('translations')->find($home->spotlight_category_id)
            : null;

        return Inertia::render('Storefront/Home/Index', [
            'hero' => [
                'title' => $home->hero_title[$locale] ?? $home->hero_title['ar'] ?? '',
                'subtitle' => $home->hero_subtitle[$locale] ?? $home->hero_subtitle['ar'] ?? '',
                'image' => $this->responsive($home->hero_image),
            ],
            'brands' => Brand::query()->active()->whereHas('media')->with(['translations', 'media'])->orderBy('sort_order')->get()
                ->map(fn (Brand $b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'url' => route('storefront.brand', ['slug' => $b->slug]),
                    'logo' => CatalogController::logoUrl($b),
                ]),
            'categories' => CategoryResource::collection(
                Category::query()->active()->whereNull('parent_id')->ordered()
                    ->with(['translations', 'media'])
                    ->withCount(['products' => fn ($q) => $q->published()])
                    ->get()
                    ->filter(fn (Category $c) => $c->products_count > 0)
                    ->values(),
            ),
            'featured' => ProductCardResource::collection($featured),
            'spotlight' => $spotlight ? [
                'title' => $home->spotlight_title[$locale] ?? $home->spotlight_title['ar'] ?? $spotlight->name,
                'text' => $home->spotlight_text[$locale] ?? $home->spotlight_text['ar'] ?? null,
                'url' => route('storefront.category', ['slug' => $spotlight->slug]),
                'images' => array_map(fn (string $path) => asset('storage/'.$path), $home->spotlight_images),
                'products' => ProductCardResource::collection(
                    $this->published()->whereHas('categories', fn ($q) => $q->whereKey($spotlight->id))->latest('id')->limit(4)->get(),
                ),
            ] : null,
            'bestSellers' => [
                'is_sales_based' => $bestSellers['is_sales_based'],
                'products' => ProductCardResource::collection($bestSellers['products']),
            ],
            'offers' => ProductCardResource::collection(
                $this->published()->whereHas('activeVariants', fn (Builder $q) => $q->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'regular_price'))
                    ->latest('id')->limit(8)->get(),
            ),
            'seo' => SeoData::make($seo->home_title[$locale] ?? $seo->home_title['ar'], $seo->home_description[$locale] ?? null)
                ->forCurrentRoute()
                ->image(($hero = end($home->hero_image)) ? asset('storage/'.$hero) : null)
                ->withJsonLd(JsonLd::organization())
                ->withJsonLd(JsonLd::website()),
        ]);
    }

    private function published(): Builder
    {
        return Product::query()->published()->with(self::CARD_RELATIONS);
    }

    /** Merchandised featured products, topped up with the newest arrivals (as the original store does). */
    private function featured(): Collection
    {
        $featured = $this->published()->where('is_featured', true)->latest('published_at')->limit(8)->get();

        return $featured->count() >= 8 ? $featured : $featured->concat(
            $this->published()->whereKeyNot($featured->modelKeys())->latest('id')->limit(8 - $featured->count())->get(),
        );
    }

    /**
     * Real best sellers from order history. Until enough orders exist we say so honestly
     * (the UI then titles the section "Picked for you" instead of "Best sellers").
     *
     * @param  list<int>  $exclude
     * @return array{is_sales_based: bool, products: Collection}
     */
    private function bestSellers(array $exclude): array
    {
        $topIds = OrderItem::query()
            ->whereNotNull('product_id')
            ->selectRaw('product_id, SUM(quantity) as sold')
            ->groupBy('product_id')
            ->orderByDesc('sold')
            ->limit(10)
            ->pluck('product_id');

        if ($topIds->count() >= 4) {
            $products = $this->published()->whereKey($topIds)->get()
                ->sortBy(fn ($p) => $topIds->search($p->getKey()))->values();

            return ['is_sales_based' => true, 'products' => $products];
        }

        return [
            'is_sales_based' => false,
            'products' => $this->published()->whereKeyNot($exclude)->inRandomOrder(crc32(now()->toDateString()))->limit(10)->get(),
        ];
    }

    /** @param array<string, string> $byWidth */
    private function responsive(array $byWidth): ?array
    {
        if ($byWidth === []) {
            return null;
        }

        ksort($byWidth, SORT_NUMERIC);

        return [
            'src' => asset('storage/'.end($byWidth)),
            'srcset' => collect($byWidth)->map(fn ($path, $width) => asset('storage/'.$path)." {$width}w")->implode(', '),
        ];
    }
}
