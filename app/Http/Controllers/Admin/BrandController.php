<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Support\TranslationInput;
use App\Tables\Admin\BrandsTable;
use Astrotomic\Translatable\Validation\RuleFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    private const FIELDS = ['name', 'slug', 'description'];

    public function index(Request $request): Response
    {
        $this->authorize('brands.view');

        return Inertia::render('Admin/Brands/Index', (new BrandsTable($request))->props('brands'));
    }

    public function create(): Response
    {
        $this->authorize('brands.create');

        return Inertia::render('Admin/Brands/Form', ['brand' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('brands.create');
        $this->save($request, new Brand);

        return to_route('admin.brands.index')->with('success', __('admin.created'));
    }

    public function edit(Brand $brand): Response
    {
        $this->authorize('brands.update');
        $brand->load(['translations', 'media']);

        return Inertia::render('Admin/Brands/Form', ['brand' => [
            'id' => $brand->id,
            'sort_order' => $brand->sort_order,
            'is_active' => $brand->is_active,
            'is_featured' => $brand->is_featured,
            'logo' => $brand->getFirstMediaUrl('logo', 'thumb') ?: $brand->getFirstMediaUrl('logo') ?: null,
            'translations' => collect(config('translatable.locales'))->mapWithKeys(fn ($l) => [
                $l => collect(self::FIELDS)->mapWithKeys(fn ($f) => [$f => $brand->translate($l, false)?->{$f} ?? '']),
            ]),
        ], 'seo_overrides' => SeoController::props($brand, 'brands')]);
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $this->authorize('brands.update');
        $this->save($request, $brand);

        return back()->with('success', __('admin.updated'));
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $this->authorize('brands.delete');
        $brand->delete();

        return to_route('admin.brands.index')->with('success', __('admin.deleted'));
    }

    private function save(Request $request, Brand $brand): void
    {
        $data = $request->validate([
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'logo' => ['nullable', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp,image/avif'],
            ...RuleFactory::make([
                'translations.%name%' => ['nullable', 'string', 'max:120'],
                'translations.%slug%' => ['nullable', 'string', 'max:160', 'regex:/^[\pL\pM\pN\-]+$/u'],
                'translations.%description%' => ['nullable', 'string', 'max:5000'],
            ]),
            'translations.ar.name' => ['required', 'string', 'max:120'],
        ]);

        DB::transaction(function () use ($brand, $data, $request) {
            $brand->fill([
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
                'is_featured' => $data['is_featured'] ?? false,
            ]);
            TranslationInput::apply($brand, $data['translations'], self::FIELDS, ['description']);
            $brand->save();

            if ($file = $request->file('logo')) {
                $brand->addMedia($file)
                    ->usingFileName('brand-'.$brand->id.'-'.Str::lower(Str::random(6)).'.'.$file->guessExtension())
                    ->toMediaCollection('logo');
            }
        });
    }
}
