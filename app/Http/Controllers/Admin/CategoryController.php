<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\TranslationInput;
use App\Tables\Admin\CategoriesTable;
use Astrotomic\Translatable\Validation\RuleFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    private const FIELDS = ['name', 'slug', 'description', 'meta_title', 'meta_description'];

    public function index(Request $request): Response
    {
        $this->authorize('categories.view');

        return Inertia::render('Admin/Categories/Index', (new CategoriesTable($request))->props('categories'));
    }

    public function create(): Response
    {
        $this->authorize('categories.create');

        return Inertia::render('Admin/Categories/Form', ['category' => null, 'parents' => $this->parents()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('categories.create');
        $this->save($request, new Category);

        return to_route('admin.categories.index')->with('success', __('admin.created'));
    }

    public function edit(Category $category): Response
    {
        $this->authorize('categories.update');
        $category->load(['translations', 'media']);

        return Inertia::render('Admin/Categories/Form', [
            'category' => [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'sort_order' => $category->sort_order,
                'is_active' => $category->is_active,
                'image' => $category->getFirstMediaUrl('image', 'thumb') ?: $category->getFirstMediaUrl('image') ?: null,
                'translations' => collect(config('translatable.locales'))->mapWithKeys(fn ($l) => [
                    $l => collect(self::FIELDS)->mapWithKeys(fn ($f) => [$f => $category->translate($l, false)?->{$f} ?? '']),
                ]),
            ],
            'parents' => $this->parents($category),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('categories.update');
        $this->save($request, $category);

        return back()->with('success', __('admin.updated'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('categories.delete');
        $category->delete(); // children become top-level (nullOnDelete); products keep other categories

        return to_route('admin.categories.index')->with('success', __('admin.deleted'));
    }

    private function save(Request $request, Category $category): void
    {
        $excluded = $category->exists ? $category->descendantAndSelfIds() : [];

        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:categories,id', Rule::notIn($excluded)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp,image/avif'],
            ...RuleFactory::make([
                'translations.%name%' => ['nullable', 'string', 'max:255'],
                'translations.%slug%' => ['nullable', 'string', 'max:160', 'regex:/^[\pL\pM\pN\-]+$/u'],
                'translations.%description%' => ['nullable', 'string', 'max:20000'],
                'translations.%meta_title%' => ['nullable', 'string', 'max:70'],
                'translations.%meta_description%' => ['nullable', 'string', 'max:170'],
            ]),
            'translations.ar.name' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($category, $data, $request) {
            $category->fill([
                'parent_id' => $data['parent_id'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ]);
            TranslationInput::apply($category, $data['translations'], self::FIELDS, ['description']);
            $category->save();

            if ($file = $request->file('image')) {
                $category->addMedia($file)
                    ->usingFileName('category-'.$category->id.'-'.Str::lower(Str::random(6)).'.'.$file->guessExtension())
                    ->toMediaCollection('image');
            }
        });
    }

    private function parents(?Category $except = null)
    {
        $excluded = $except ? $except->descendantAndSelfIds() : [];

        return Category::query()->with('translations')->ordered()->whereNotIn('id', $excluded)->get()
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name]);
    }
}
