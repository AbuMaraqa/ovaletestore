<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Queries\CatalogQuery;
use App\Support\CurrencyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Live search suggestions for the search overlay (published data only, throttled). */
class SearchController extends Controller
{
    public function suggest(Request $request, CatalogQuery $catalog): JsonResponse
    {
        $q = trim((string) $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']])['q']);
        $like = '%'.addcslashes($q, '%_\\').'%';

        $products = $catalog->query(['q' => $q, 'sort' => 'newest'])->limit(8)->get()->map(function (Product $p) {
            $image = $p->getFirstMedia('gallery');
            $price = (int) $p->activeVariants->map->effectivePrice()->min();

            return [
                'id' => $p->id,
                'name' => $p->name,
                'url' => route('storefront.product', ['slug' => $p->slug]),
                'image' => $image ? ($image->hasGeneratedConversion('thumb') ? $image->getUrl('thumb') : $image->getUrl()) : null,
                'price' => app(CurrencyContext::class)->format($price),
            ];
        });

        $categories = Category::query()->active()->with('translations')
            ->whereHas('translations', fn (Builder $t) => $t->where('name', 'like', $like))
            ->limit(4)->get()
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name, 'url' => route('storefront.category', ['slug' => $c->slug])]);

        return response()->json([
            'products' => $products,
            'categories' => $categories,
            'all_url' => route('storefront.shop', ['q' => $q]),
        ]);
    }
}
