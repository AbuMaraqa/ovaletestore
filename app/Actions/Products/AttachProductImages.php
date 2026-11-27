<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\User;
use App\Support\StagedUploads;
use Illuminate\Support\Str;

/**
 * Adds staged images (App\Support\StagedUploads ids, in the order the staff member arranged them) to a product's
 * gallery on save. The files were already validated when they were staged (fileRules()): real MIME sniffing, size
 * and dimension limits. Generated file names (no user-controlled paths), originals kept, conversions queued.
 */
class AttachProductImages
{
    public const MAX_FILES = 20;

    /** @return list<string> rules for one uploaded image */
    public static function fileRules(): array
    {
        return [
            'required', 'file', 'max:10240',
            'mimetypes:image/jpeg,image/png,image/webp,image/avif',
            'dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000',
        ];
    }

    /** @param list<string> $uploadIds */
    public function handle(Product $product, User $user, array $uploadIds): int
    {
        $baseName = Str::slug((string) ($product->translate('en', false)?->getAttribute('name') ?? 'product')) ?: 'product';
        $added = 0;

        foreach ($uploadIds as $id) {
            $path = StagedUploads::path($user, $id);
            if (! $path) {
                continue; // validated just before; vanished only if pruned in between
            }
            $product->addMedia($path) // moves the staged file into the media library
                ->usingName((string) $product->translate('ar', false)?->getAttribute('name'))
                ->usingFileName(Str::limit($baseName, 60, '').'-'.Str::lower(Str::random(8)).'.'.pathinfo($path, PATHINFO_EXTENSION))
                ->toMediaCollection('gallery');
            $added++;
        }

        if ($added > 0) {
            activity('catalog')->event('images_added')->performedOn($product)
                ->withProperties(['count' => $added, 'label' => (string) $product->translate('ar', true)?->getAttribute('name')])
                ->log('images_added');
        }

        return $added;
    }
}
