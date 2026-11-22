<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\StockStatus;
use App\Models\AttributeTerm;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Support\Money;
use Astrotomic\Translatable\Validation\RuleFactory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Prices arrive in major units ("12.50") and are converted to minor units in validatedProduct().
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? $this->user()->can('update', $product)
            : $this->user()->can('create', Product::class);
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->id : null;

        return [
            'type' => ['required', Rule::enum(ProductType::class)],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'is_featured' => ['boolean'],
            'published_at' => ['nullable', 'date'],

            'translations' => ['required', 'array'],
            ...RuleFactory::make([
                'translations.%name%' => ['nullable', 'string', 'max:255'],
                'translations.%slug%' => ['nullable', 'string', 'max:160', 'regex:/^[\pL\pM\pN\-]+$/u'],
                'translations.%short_description%' => ['nullable', 'string', 'max:5000'],
                'translations.%description%' => ['nullable', 'string', 'max:100000'],
                'translations.%meta_title%' => ['nullable', 'string', 'max:70'],
                'translations.%meta_description%' => ['nullable', 'string', 'max:170'],
            ]),
            'translations.ar.name' => ['required', 'string', 'max:255'],

            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'primary_category_id' => ['nullable', 'integer', Rule::in($this->input('category_ids', []))],

            'attributes' => ['array'],
            'attributes.*.attribute_id' => ['required', 'integer', 'distinct', 'exists:attributes,id'],
            'attributes.*.term_ids' => ['required', 'array', 'min:1'],
            'attributes.*.term_ids.*' => ['integer', 'exists:attribute_terms,id'],
            'attributes.*.used_for_variations' => ['boolean'],
            'attributes.*.is_visible' => ['boolean'],

            'variants' => ['required', 'array', 'min:1', 'max:200'],
            'variants.*.term_ids' => ['array'],
            'variants.*.term_ids.*' => ['integer'],
            'variants.*.sku' => [
                'nullable', 'string', 'max:64', 'distinct',
                Rule::unique('product_variants', 'sku')->where(fn ($q) => $productId ? $q->where('product_id', '!=', $productId) : $q),
            ],
            'variants.*.regular_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'variants.*.sale_price' => ['nullable', 'numeric', 'min:0', 'lt:variants.*.regular_price'],
            'variants.*.sale_starts_at' => ['nullable', 'date'],
            'variants.*.sale_ends_at' => ['nullable', 'date', 'after:variants.*.sale_starts_at'],
            'variants.*.manage_stock' => ['boolean'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:-100000', 'max:1000000'],
            'variants.*.stock_status' => ['required', Rule::enum(StockStatus::class)],
            'variants.*.weight_grams' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'variants.*.length_mm' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'variants.*.width_mm' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'variants.*.height_mm' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'variants.*.is_active' => ['boolean'],
            'variants.*.is_default' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $this->validateTermsBelongToAttributes($validator);
            $this->validateTranslatedSlugsAreUnique($validator);
        }];
    }

    /** @return array<string, mixed> */
    public function validatedProduct(): array
    {
        $data = $this->validated();

        $data['variants'] = collect($data['variants'])->map(function (array $variant) {
            $variant['regular_price'] = Money::toMinor($variant['regular_price']);
            $variant['sale_price'] = Money::toMinor($variant['sale_price'] ?? null);

            return $variant;
        })->all();

        return $data;
    }

    private function validateTermsBelongToAttributes(Validator $validator): void
    {
        foreach ((array) $this->input('attributes', []) as $i => $row) {
            $termIds = array_map('intval', (array) ($row['term_ids'] ?? []));
            $valid = AttributeTerm::query()->where('attribute_id', $row['attribute_id'] ?? 0)->whereIn('id', $termIds)->count();

            if ($valid !== count(array_unique($termIds))) {
                $validator->errors()->add("attributes.{$i}.term_ids", __('validation.exists', ['attribute' => __('admin.terms')]));
            }
        }
    }

    private function validateTranslatedSlugsAreUnique(Validator $validator): void
    {
        $product = $this->route('product');

        foreach ((array) $this->input('translations', []) as $locale => $fields) {
            if (blank($fields['slug'] ?? null)) {
                continue;
            }

            $taken = ProductTranslation::query()
                ->where('locale', $locale)
                ->where('slug', $fields['slug'])
                ->when($product instanceof Product, fn ($q) => $q->where('product_id', '!=', $product->id))
                ->exists();

            if ($taken) {
                $validator->errors()->add("translations.{$locale}.slug", __('validation.unique', ['attribute' => __('admin.slug')]));
            }
        }
    }
}
