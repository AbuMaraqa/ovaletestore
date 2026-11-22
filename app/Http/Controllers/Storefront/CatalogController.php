<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\CategoryResource;
use App\Http\Resources\Storefront\ProductCardResource;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Queries\CatalogQuery;
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
        if ($filters['q'] || $filters['sort'] !== 'newest' || $filters['in_stock']) {
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

        if ($filters['q'] || $filters['sort'] !== 'newest' || $filters['in_stock']) {
            $seo->noindex();
        }

        return $this->render($request, $filters, $category, $seo);
    }

    private function render(Request $request, array $filters, ?Category $category, SeoData $seo): Response
    {
        return Inertia::render('Storefront/Catalog/Index', [
            'products' => ProductCardResource::collection($this->catalog->paginate($filters)),
            'category' => $category ? [
                'name' => $category->name,
                'description' => $category->description,
                'url' => route('storefront.category', ['slug' => $category->slug]),
            ] : null,
            'categories' => CategoryResource::collection(
                Category::query()->active()->whereNull('parent_id')->ordered()->with('translations')->get(),
            ),
            'filters' => [
                'q' => $filters['q'],
                'sort' => $filters['sort'],
                'in_stock' => $filters['in_stock'],
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
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        return [
            'q' => $validated['q'] ?? null,
            'sort' => $validated['sort'] ?? 'newest',
            'in_stock' => (bool) ($validated['in_stock'] ?? false),
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
