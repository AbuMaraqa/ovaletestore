<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        [$class, $permission] = self::TYPES[$type] ?? abort(404);
        $this->authorize($permission);
        /** @var Product|Category|Brand $model */
        $model = $class::query()->findOrFail($id);

        $data = $request->validate([
            'robots' => ['nullable', Rule::in(Seo::ROBOTS)],
            'canonical_url' => ['nullable', 'url:https,http', 'max:255'],
            'image' => ['nullable', 'file', 'max:4096', 'mimetypes:image/jpeg,image/png,image/webp', 'dimensions:min_width=600,min_height=315'],
            'remove_image' => ['boolean'],
        ]);

        /** @var Seo $seo */
        $seo = $model->seo()->firstOrNew();
        $image = $seo->image;
        if ($image && ($request->boolean('remove_image') || $request->hasFile('image'))) {
            Storage::disk('public')->delete($image);
            $image = null;
        }
        if ($file = $request->file('image')) {
            $image = $file->storeAs('seo', Str::random(24).'.'.$file->extension(), 'public') ?: null;
        }

        $seo->fill([
            'robots' => $data['robots'] ?? null,
            'canonical_url' => $data['canonical_url'] ?? null,
            'image' => $image,
        ])->save();

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
