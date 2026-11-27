<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Seo\SaveSeoOverrides;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * The search-engine card on the product / category / brand edit screens: the record's laravel-seo row
 * (robots, canonical URL, share image). Text (meta title/description) stays per language on the main form.
 */
class SeoController extends Controller
{
    /** type (URL segment) => [model, permission needed to edit it] */
    public const TYPES = [
        'products' => [Product::class, 'products.update'],
        'categories' => [Category::class, 'categories.update'],
        'brands' => [Brand::class, 'brands.update'],
    ];

    public function update(Request $request, string $type, int $id, SaveSeoOverrides $save): RedirectResponse
    {
        [$class, $permission] = self::TYPES[$type] ?? abort(404);
        $this->authorize($permission);
        /** @var Product|Category|Brand $model */
        $model = $class::query()->findOrFail($id);

        $data = $request->validate([
            ...SaveSeoOverrides::rules(),
            'image' => ['nullable', 'file', 'max:4096', 'mimetypes:image/jpeg,image/png,image/webp', 'dimensions:min_width=600,min_height=315'],
        ]);
        $save->handle($model, $data, $request->file('image'));

        return back()->with('success', __('admin.updated'));
    }

    /**
     * Props for the card (`seo_overrides`). Not called `seo`: that prop name is the page <head> (Inertia serverHead).
     *
     * @return array<string, mixed>
     */
    public static function props(Product|Category|Brand $model, string $type): array
    {
        $seo = $model->seo;

        return [
            'update_url' => route('admin.seo.update', ['type' => $type, 'id' => $model->getKey()]),
            'robots' => $seo->robots,
            'canonical_url' => $seo->canonical_url,
            'image' => $seo->image ? Storage::disk('public')->url($seo->image) : null,
            'robots_options' => Seo::ROBOTS,
            'urls' => collect(array_keys(LaravelLocalization::getSupportedLocales()))
                ->mapWithKeys(fn (string $locale) => [$locale => rawurldecode($model->pageUrl($locale))]),
        ];
    }
}
