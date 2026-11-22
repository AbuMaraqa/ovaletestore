<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Secure uploads: real MIME sniffing (mimetypes), size + dimension limits, generated file names
 * (no user-controlled paths), originals preserved, conversions queued.
 */
class ProductMediaController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $request->validate([
            'images' => ['required', 'array', 'max:10'],
            'images.*' => [
                'required', 'file', 'max:10240',
                'mimetypes:image/jpeg,image/png,image/webp,image/avif',
                'dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000',
            ],
        ]);

        $baseName = Str::slug((string) ($product->translate('en', false)?->name ?? 'product')) ?: 'product';

        foreach ($request->file('images') as $file) {
            $product->addMedia($file)
                ->usingName((string) $product->translate('ar', false)?->name)
                ->usingFileName(Str::limit($baseName, 60, '').'-'.Str::lower(Str::random(8)).'.'.$file->guessExtension())
                ->toMediaCollection('gallery');
        }

        return back()->with('success', __('admin.updated'));
    }

    public function reorder(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        $owned = $product->getMedia('gallery')->pluck('id')->all();

        Media::setNewOrder(array_values(array_intersect($ids, $owned)));

        return back();
    }

    public function destroy(Product $product, Media $media): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_unless($media->model_type === $product->getMorphClass() && (int) $media->model_id === $product->id, 404);

        $media->delete();

        return back()->with('success', __('admin.deleted'));
    }
}
