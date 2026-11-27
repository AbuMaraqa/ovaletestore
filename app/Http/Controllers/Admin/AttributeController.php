<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttributeDisplayType;
use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeTerm;
use App\Support\TranslationInput;
use App\Tables\Admin\AttributesTable;
use Astrotomic\Translatable\Validation\RuleFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Global (reusable) attributes and their terms, edited together in one form. */
class AttributeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('attributes.view');

        return Inertia::render('Admin/Attributes/Index', (new AttributesTable($request))->props('attributes'));
    }

    public function create(): Response
    {
        $this->authorize('attributes.create');

        return Inertia::render('Admin/Attributes/Form', ['attribute' => null, 'displayTypes' => array_column(AttributeDisplayType::cases(), 'value')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('attributes.create');
        $attribute = $this->save($request, new Attribute);

        return to_route('admin.attributes.edit', $attribute)->with('success', __('admin.created'));
    }

    public function edit(Attribute $attribute): Response
    {
        $this->authorize('attributes.update');
        abort_unless($attribute->isGlobal(), 404);
        $attribute->load(['translations', 'terms.translations']);

        $names = fn ($model) => collect(config('translatable.locales'))->mapWithKeys(fn ($l) => [$l => ['name' => $model->translate($l, false)?->name ?? '']]);

        return Inertia::render('Admin/Attributes/Form', [
            'attribute' => [
                'id' => $attribute->id,
                'code' => $attribute->code,
                'display_type' => $attribute->display_type->value,
                'sort_order' => $attribute->sort_order,
                'translations' => $names($attribute),
                'terms' => $attribute->terms->map(fn (AttributeTerm $t) => [
                    'id' => $t->id,
                    'code' => $t->code,
                    'color_hex' => $t->color_hex,
                    'translations' => $names($t),
                ]),
            ],
            'displayTypes' => array_column(AttributeDisplayType::cases(), 'value'),
        ]);
    }

    public function update(Request $request, Attribute $attribute): RedirectResponse
    {
        $this->authorize('attributes.update');
        abort_unless($attribute->isGlobal(), 404);
        $this->save($request, $attribute);

        return back()->with('success', __('admin.updated'));
    }

    public function destroy(Attribute $attribute): RedirectResponse
    {
        $this->authorize('attributes.delete');
        abort_unless($attribute->isGlobal(), 404);
        $attribute->delete();

        return to_route('admin.attributes.index')->with('success', __('admin.deleted'));
    }

    private function save(Request $request, Attribute $attribute): Attribute
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:64', 'alpha_dash:ascii',
                Rule::unique('attributes', 'code')->whereNull('product_id')->ignore($attribute->id)],
            'display_type' => ['required', Rule::enum(AttributeDisplayType::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            ...RuleFactory::make(['translations.%name%' => ['nullable', 'string', 'max:120']]),
            'translations.ar.name' => ['required', 'string', 'max:120'],
            'terms' => ['array', 'max:200'],
            'terms.*.id' => ['nullable', 'integer'],
            'terms.*.color_hex' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'terms.*.translations.ar.name' => ['required', 'string', 'max:120'],
            'terms.*.translations.en.name' => ['nullable', 'string', 'max:120'],
            'terms.*.translations.he.name' => ['nullable', 'string', 'max:120'],
        ]);

        return DB::transaction(function () use ($attribute, $data) {
            $attribute->fill([
                'code' => $data['code'] ?: Str::slug($data['translations']['en']['name'] ?? '') ?: 'attr-'.Str::lower(Str::random(6)),
                'display_type' => $data['display_type'],
                'sort_order' => $data['sort_order'] ?? 0,
            ]);
            TranslationInput::apply($attribute, $data['translations'], ['name']);
            $attribute->save();

            $keep = [];
            foreach (array_values($data['terms'] ?? []) as $i => $row) {
                $term = isset($row['id']) ? $attribute->terms()->find($row['id']) : null;
                $term ??= new AttributeTerm(['attribute_id' => $attribute->id]);
                $code = $term->code ?: (Str::slug($row['translations']['en']['name'] ?? '') ?: 'term');
                if (! $term->exists && $attribute->terms()->where('code', $code)->exists()) {
                    $code .= '-'.Str::lower(Str::random(4));
                }
                $term->fill([
                    'code' => $code,
                    'color_hex' => $row['color_hex'] ?? null,
                    'sort_order' => $i,
                ]);
                TranslationInput::apply($term, $row['translations'], ['name']);
                $term->save();
                $keep[] = $term->id;
            }

            // Removing a term also removes it from products/variants (cascade) — intended.
            $attribute->terms()->whereNotIn('id', $keep)->delete();

            return $attribute;
        });
    }
}
