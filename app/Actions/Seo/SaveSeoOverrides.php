<?php

namespace App\Actions\Seo;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seo;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Saves a record's laravel-seo row (App\Models\Seo): robots, canonical URL and share image.
 * Used by the product form (saved together with the product) and the category / brand search-engine card.
 */
class SaveSeoOverrides
{
    /** @return array<string, list<mixed>> rules under a prefix, e.g. "seo." */
    public static function rules(string $prefix = ''): array
    {
        return [
            $prefix.'robots' => ['nullable', Rule::in(Seo::ROBOTS)],
            $prefix.'canonical_url' => ['nullable', 'url:https,http', 'max:255'],
            $prefix.'remove_image' => ['boolean'],
        ];
    }

    /** @param array{robots?: string|null, canonical_url?: string|null, remove_image?: bool} $data */
    public function handle(Product|Category|Brand $model, array $data, UploadedFile|File|null $image = null): Seo
    {
        $seo = $model->seo()->firstOrNew();
        $path = $seo->image;

        if ($path && (! empty($data['remove_image']) || $image)) {
            Storage::disk('public')->delete($path);
            $path = null;
        }
        if ($image) {
            $extension = $image instanceof UploadedFile ? $image->extension() : ($image->guessExtension() ?? 'jpg');
            $path = Storage::disk('public')->putFileAs('seo', $image, Str::random(24).'.'.$extension) ?: null;
        }

        $seo->fill([
            'robots' => $data['robots'] ?? null,
            'canonical_url' => $data['canonical_url'] ?? null,
            'image' => $path,
        ])->save();

        return $seo;
    }
}
