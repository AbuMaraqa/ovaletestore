<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\CategoryResource;
use App\Http\Resources\Storefront\ProductCardResource;
use App\Models\Brand;
use App\Models\BrandTranslation;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Queries\CatalogQuery;
use App\Support\CurrencyContext;
use App\Support\Money;
use App\Support\Seo\JsonLd;
use App\Support\Seo\SeoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class CatalogController extends Controller
{
    public function __construct(private readonly CatalogQuery $catalog) {}

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $seo = SeoData::make($filters['q'] ? __('storefront.results_for', ['q' => $filters['q']]) : __('storefront.shop'))
            ->forCurrentRoute(['page']);

        // Search result pages are thin/duplicate content.
        if ($filters['q'] || $filters['sort'] !== 'newest' || $filters['in_stock'] || $filters['on_sale'] || $filters['brand_ids'] || $filters['price_min'] !== null || $filters['price_max'] !== null) {
            $seo->noindex();
        }

        return $this->render($request, $filters, null, $seo);
    }

    public function category(Request $request, string $slug): Response|RedirectResponse
    {
        $category = $this->resolveCategory($slug);

        if (($canonical = $category->translate(null, true)?->slug) && $canonical !== $slug) {
            return redirect()->route('storefront.category', ['slug' => $canonical], 301);
        }

        $filters = $this->filters($request) + ['category' => $category];

        $seo = SeoData::make($category->meta_title ?: $category->name, $category->meta_description ?: $category->description)
            ->localized(fn (string $locale) => LaravelLocalization::getLocalizedURL(
                $locale,
                route('storefront.category', ['slug' => $category->translate($locale, true)->slug]),
                [],
                false,
            ).($request->integer('page') > 1 ? '?page='.$request->integer('page') : ''))
            ->withJsonLd(JsonLd::breadcrumbs([
                ['name' => __('common.home'), 'url' => route('storefront.home')],
                ['name' => $category->name, 'url' => route('storefront.category', ['slug' => $category->slug])],
            ]));

        if ($filters['q'] || $filters['sort'] !== 'newest' || $filters['in_stock'] || $filters['on_sale'] || $filters['brand_ids'] || $filters['price_min'] !== null || $filters['price_max'] !== null) {
            $seo->noindex();
        }

        return $this->render($request, $filters, $category, $seo);
    }

    public function brand(Request $request, string $slug): Response|RedirectResponse
    {
        $translation = BrandTranslation::query()
            ->where('slug', rawurldecode($slug))
            ->orderByRaw('locale = ? desc', [app()->getLocale()])
            ->firstOrFail();
        $brand = Brand::query()->active()->with(['translations', 'media'])->findOrFail($translation->brand_id);

        if ($brand->slug !== rawurldecode($slug)) {
            return redirect()->route('storefront.brand', ['slug' => $brand->slug], 301);
        }

        $filters = $this->filters($request) + ['brand_id' => $brand->id];
        $url = route('storefront.brand', ['slug' => $brand->slug]);

        $seo = SeoData::make($brand->name, $brand->description ?: __('storefront.brand_products', ['brand' => $brand->name]))
            ->localized(fn (string $locale) => LaravelLocalization::getLocalizedURL(
                $locale,
                route('storefront.brand', ['slug' => $brand->translate($locale, true)->slug]),
                [],
                false,
            ))
            ->withJsonLd(JsonLd::breadcrumbs([
                ['name' => __('common.home'), 'url' => route('storefront.home')],
                ['name' => __('storefront.brands'), 'url' => route('storefront.brands')],
                ['name' => $brand->name, 'url' => $url],
            ]));

        if ($filters['q'] || $filters['sort'] !== 'newest' || $filters['in_stock'] || $filters['on_sale'] || $filters['brand_ids'] || $filters['price_min'] !== null || $filters['price_max'] !== null) {
            $seo->noindex();
        }

        return $this->render($request, $filters, null, $seo, [
            'name' => $brand->name,
            'description' => $brand->description,
            'url' => $url,
            'logo' => self::logoUrl($brand),
        ]);
    }

    /** All categories as large image cards, with product and live-offer counts (marketing entry page). */
    public function categories(): Response
    {
        $onSale = fn ($variants) => $variants->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'regular_price')
            ->where(fn ($q) => $q->whereNull('sale_starts_at')->orWhere('sale_starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>', now()));

        $categories = Category::query()->active()->whereNull('parent_id')->ordered()
            ->with(['translations', 'media'])
            ->withCount([
                'products' => fn ($q) => $q->published(),
                'products as on_sale_count' => fn ($q) => $q->published()->whereHas('activeVariants', $onSale),
            ])
            ->get()
            ->filter(fn (Category $c) => $c->products_count > 0)
            ->values();

        return Inertia::render('Storefront/Categories/Index', [
            'categories' => $categories->map(fn (Category $c) => [
                ...(new CategoryResource($c))->resolve(),
                'on_sale_count' => (int) $c->getAttribute('on_sale_count'),
                'sale_url' => route('storefront.category', ['slug' => $c->slug, 'sale' => 1]),
            ]),
            'seo' => SeoData::make(__('storefront.categories_title'), __('storefront.categories_subtitle'))
                ->forCurrentRoute()
                ->withJsonLd(JsonLd::breadcrumbs([
                    ['name' => __('common.home'), 'url' => route('storefront.home')],
                    ['name' => __('storefront.categories'), 'url' => route('storefront.categories')],
                ])),
        ]);
    }

    public function brands(): Response
    {
        return Inertia::render('Storefront/Brands/Index', [
            'brands' => Brand::query()->active()->with(['translations', 'media'])
                ->withCount(['products' => fn ($q) => $q->published()])
                ->orderBy('sort_order')->get()
                ->map(fn (Brand $b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'url' => route('storefront.brand', ['slug' => $b->slug]),
                    'logo' => self::logoUrl($b),
                    'products_count' => $b->products_count,
                ]),
            'seo' => SeoData::make(__('storefront.brands'), __('storefront.brands_subtitle'))->forCurrentRoute(),
        ]);
    }

    public static function logoUrl(Brand $brand): ?string
    {
        $logo = $brand->getFirstMedia('logo');

        return $logo ? ($logo->hasGeneratedConversion('thumb') ? $logo->getUrl('thumb') : $logo->getUrl()) : null;
    }

    private function render(Request $request, array $filters, ?Category $category, SeoData $seo, ?array $brand = null): Response
    {
        return Inertia::render('Storefront/Catalog/Index', [
            'products' => ProductCardResource::collection($this->catalog->paginate($filters)),
            'category' => $category ? [
                'name' => $category->name,
                'description' => $category->description,
                'url' => route('storefront.category', ['slug' => $category->slug]),
            ] : null,
            'brand' => $brand,
            'facets' => $this->facets(),
            'filters' => [
                'q' => $filters['q'],
                'sort' => $filters['sort'],
                'in_stock' => $filters['in_stock'],
                'sale' => $filters['on_sale'],
                'brands' => $filters['brand_ids'],
                // Echo what the visitor typed (their currency), not the converted base amount.
                'price_min' => $filters['price_min'] !== null ? Money::toMajor(Money::toMinor((string) $request->query('price_min'))) : null,
                'price_max' => $filters['price_max'] !== null ? Money::toMajor(Money::toMinor((string) $request->query('price_max'))) : null,
            ],
            'sorts' => CatalogQuery::SORTS,
            'seo' => $seo,
        ]);
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(CatalogQuery::SORTS)],
            'in_stock' => ['nullable', 'boolean'],
            'sale' => ['nullable', 'boolean'],
            'brands' => ['nullable', 'array', 'max:30'],
            'brands.*' => ['integer'],
            'price_min' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        return [
            'q' => $validated['q'] ?? null,
            'sort' => $validated['sort'] ?? 'newest',
            'in_stock' => (bool) ($validated['in_stock'] ?? false),
            'on_sale' => (bool) ($validated['sale'] ?? false),
            'brand_ids' => array_values(array_unique(array_map('intval', $validated['brands'] ?? []))),
            'price_min' => $this->priceToBase($validated['price_min'] ?? null),
            'price_max' => $this->priceToBase($validated['price_max'] ?? null),
        ];
    }

    /** A price typed in the visitor's currency → base minor units (prices are stored in the base currency). */
    private function priceToBase(string|int|float|null $amount): ?int
    {
        $minor = Money::toMinor($amount);

        return $minor === null ? null : app(CurrencyContext::class)->toBase($minor);
    }

    /** Sidebar facets: categories and brands with published-product counts. */
    private function facets(): array
    {
        $published = fn ($q) => $q->published();

        return [
            'categories' => Category::query()->active()->whereNull('parent_id')->ordered()->with('translations')
                ->withCount(['products' => $published])->get()
                ->filter(fn (Category $c) => $c->products_count > 0)
                ->map(fn (Category $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'url' => route('storefront.category', ['slug' => $c->slug]),
                    'count' => $c->products_count,
                ])->values(),
            'brands' => Brand::query()->active()->with('translations')
                ->withCount(['products' => $published])->orderByDesc('products_count')->get()
                ->filter(fn (Brand $b) => $b->products_count > 0)
                ->map(fn (Brand $b) => ['id' => $b->id, 'name' => $b->name, 'count' => $b->products_count])->values(),
        ];
    }

    /** Slug lookup: current locale first, then any locale (shared/legacy links), never inactive categories. */
    private function resolveCategory(string $slug): Category
    {
        $slug = rawurldecode($slug);
        $translation = CategoryTranslation::query()
            ->where('slug', $slug)
            ->orderByRaw('locale = ? desc', [app()->getLocale()])
            ->firstOrFail();

        return Category::query()->active()->with(['translations', 'media'])->findOrFail($translation->category_id);
    }
}
