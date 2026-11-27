<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\AttachProductImages;
use App\Actions\Products\SaveProduct;
use App\Actions\Seo\SaveSeoOverrides;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Resources\Admin\ProductFormResource;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PriceTier;
use App\Models\Product;
use App\Support\StagedUploads;
use App\Tables\Admin\ProductsTable;
use Illuminate\Http\File;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        return Inertia::render('Admin/Products/Index', (new ProductsTable($request))->props('products'));
    }

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Admin/Products/Form', [...$this->formOptions(), 'product' => null]);
    }

    /** One request: the product, its translations, variants and the images picked on the add screen. */
    public function store(ProductRequest $request, SaveProduct $save, AttachProductImages $attach): RedirectResponse
    {
        $product = DB::transaction(function () use ($request, $save, $attach) {
            $product = $save->handle($request->validatedProduct());
            $this->saveExtras($request, $product, $attach);

            return $product;
        });

        // "Save & add another" keeps the staff member on a fresh add screen.
        return ($request->boolean('add_another') ? to_route('admin.products.create') : to_route('admin.products.edit', $product))
            ->with('success', __('admin.created'));
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
            'seo_overrides' => SeoController::props($product, 'products'),
        ]);
    }

    public function update(ProductRequest $request, Product $product, SaveProduct $save, AttachProductImages $attach): RedirectResponse
    {
        DB::transaction(function () use ($request, $product, $save, $attach) {
            $save->handle($request->validatedProduct(), $product);
            $this->saveExtras($request, $product, $attach);
        });

        return back()->with('success', __('admin.updated'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);
        $product->delete(); // soft delete: order history keeps its references

        return to_route('admin.products.index')->with('success', __('admin.deleted'));
    }

    /** Gallery images and search-engine overrides picked on the same screen (staged uploads → attached here). */
    private function saveExtras(ProductRequest $request, Product $product, AttachProductImages $attach): void
    {
        $user = $request->user();
        $attach->handle($product, $user, array_values($request->input('uploads', [])));

        if (is_array($seo = $request->validated('seo'))) {
            $staged = is_string($seo['image_upload'] ?? null) ? StagedUploads::path($user, $seo['image_upload']) : null;
            app(SaveSeoOverrides::class)->handle($product, $seo, $staged ? new File($staged) : null);
            if ($staged) {
                @unlink($staged);
            }
        }
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
