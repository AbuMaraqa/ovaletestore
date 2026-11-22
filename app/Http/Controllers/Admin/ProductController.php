<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\SaveProduct;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Resources\Admin\ProductFormResource;
use App\Http\Resources\Admin\ProductListResource;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->with(['translations', 'variants', 'media', 'categories.translations'])
            ->allowedFilters(...[
                AllowedFilter::exact('status'),
                AllowedFilter::callback('category', fn (Builder $q, $id) => $q->whereHas('categories', fn ($c) => $c->where('categories.id', $id))),
                AllowedFilter::callback('search', function (Builder $q, $term) {
                    $like = '%'.addcslashes((string) $term, '%_\\').'%';
                    $q->where(fn ($w) => $w->whereHas('translations', fn ($t) => $t->where('name', 'like', $like))
                        ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $like)));
                }),
            ])
            ->allowedSorts(...['id', 'created_at', 'updated_at', AllowedSort::field('status')])
            ->defaultSort('-id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Products/Index', [
            'products' => ProductListResource::collection($products),
            'filters' => $request->only(['filter', 'sort']),
            'categories' => $this->categoryOptions(),
            'statuses' => array_column(ProductStatus::cases(), 'value'),
        ]);
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
            'productAttributes.terms', 'variants.terms', 'variants.media',
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
        ];
    }

    private function categoryOptions()
    {
        return Category::query()->with('translations')->ordered()->get()
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name, 'parent_id' => $c->parent_id]);
    }
}
