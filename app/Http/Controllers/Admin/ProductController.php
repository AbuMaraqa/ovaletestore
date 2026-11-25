<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\BulkUpdateProducts;
use App\Actions\Products\SaveProduct;
use App\Enums\ProductStatus;
use App\Enums\StockStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Resources\Admin\ProductFormResource;
use App\Http\Resources\Admin\ProductListResource;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PriceTier;
use App\Models\Product;
use App\Support\DataTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $products = QueryBuilder::for(Product::class)
            ->with(['translations', 'variants', 'media', 'categories.translations', 'brand.translations'])
            ->allowedFilters(...[
                AllowedFilter::exact('status'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('brand', 'brand_id'),
                AllowedFilter::callback('featured', fn (Builder $q, $v) => $q->where('is_featured', $v === 'yes')),
                AllowedFilter::callback('stock', fn (Builder $q, $v) => $v === 'out'
                    ? $q->whereDoesntHave('variants', fn ($w) => $w->where('stock_status', '!=', StockStatus::OutOfStock))
                    : $q->whereHas('variants', fn ($w) => $w->where('stock_status', '!=', StockStatus::OutOfStock))),
                AllowedFilter::callback('category', fn (Builder $q, $id) => $q->whereHas('categories', fn ($c) => $c->where('categories.id', $id))),
                AllowedFilter::callback('search', function (Builder $q, $term) {
                    $like = DataTable::like($term);
                    $q->where(fn ($w) => $w->whereHas('translations', fn ($t) => $t->where('name', 'like', $like))
                        ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $like)));
                }),
                ...DataTable::dateRange('created_at'),
            ])
            ->allowedSorts(...['id', 'created_at', 'updated_at', AllowedSort::field('status'), AllowedSort::field('is_featured')])
            ->defaultSort('-id')
            ->paginate(DataTable::perPage($request))
            ->withQueryString();

        return Inertia::render('Admin/Products/Index', [
            'products' => ProductListResource::collection($products),
            'state' => DataTable::state($request),
            'categories' => $this->categoryOptions(),
            'brands' => Brand::query()->with('translations')->orderBy('sort_order')->get()->map(fn (Brand $b) => ['value' => $b->id, 'label' => $b->name]),
            'statuses' => array_column(ProductStatus::cases(), 'value'),
        ]);
    }

    /** Bulk actions from the list: publish / draft / archive / delete the selected products. */
    /** Bulk actions from the list (publish / draft / archive / delete the selected products). */
    public function bulk(Request $request, BulkUpdateProducts $bulk): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(BulkUpdateProducts::ACTIONS)],
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer'],
        ]);
        $this->authorize($data['action'] === 'delete' ? 'products.delete' : 'products.update');

        $count = $bulk->handle($data['action'], array_map('intval', $data['ids']));

        return back()->with('success', __('admin.bulk_done', ['count' => $count]));
    }

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Admin/Products/Form', [...$this->formOptions(), 'product' => null]);
    }

    public function store(ProductRequest $request, SaveProduct $save): RedirectResponse
    {
        $product = $save->handle($request->validatedProduct());

        return to_route('admin.products.edit', $product)->with('success', __('admin.created'));
    }

    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);

        $product->load([
            'translations', 'categories', 'media',
            'productAttributes.terms', 'variants.terms', 'variants.media', 'variants.tierPrices',
        ]);

        return Inertia::render('Admin/Products/Form', [
            ...$this->formOptions(),
            'product' => new ProductFormResource($product),
        ]);
    }

    public function update(ProductRequest $request, Product $product, SaveProduct $save): RedirectResponse
    {
        $save->handle($request->validatedProduct(), $product);

        return back()->with('success', __('admin.updated'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);
        $product->delete(); // soft delete: order history keeps its references

        return to_route('admin.products.index')->with('success', __('admin.deleted'));
    }

    private function formOptions(): array
    {
        return [
            'categories' => $this->categoryOptions(),
            'brands' => Brand::query()->with('translations')->orderBy('sort_order')->get()
                ->map(fn (Brand $b) => ['id' => $b->id, 'name' => $b->name]),
            'attributes' => Attribute::query()->global()->with(['translations', 'terms.translations'])->orderBy('sort_order')->get()
                ->map(fn (Attribute $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'terms' => $a->terms->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]),
                ]),
            'locales' => config('translatable.locales'),
            'tiers' => PriceTier::query()->orderBy('sort_order')->get()->map(fn (PriceTier $t) => [
                'id' => $t->id, 'name' => $t->label(), 'discount_percent' => (float) $t->discount_percent, 'is_active' => $t->is_active,
            ]),
        ];
    }

    private function categoryOptions()
    {
        return Category::query()->with('translations')->ordered()->get()
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name, 'parent_id' => $c->parent_id]);
    }
}
