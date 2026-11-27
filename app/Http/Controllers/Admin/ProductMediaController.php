<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Gallery of an existing product: reorder, remove. New images are staged (StagedUploadController) and attached on save.
 */
class ProductMediaController extends Controller
{
    public function reorder(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        $owned = $product->getMedia('gallery')->pluck('id')->all();

        Media::setNewOrder(array_values(array_intersect($ids, $owned)));
        $this->log($product, 'images_reordered');

        return back();
    }

    public function destroy(Product $product, Media $media): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_unless($media->model_type === $product->getMorphClass() && (int) $media->model_id === $product->id, 404);

        $media->delete();
        $this->log($product, 'image_removed', ['file' => $media->file_name]);

        return back()->with('success', __('admin.deleted'));
    }

    /** Gallery changes are media rows, not product attributes: recorded on the product's history by hand. */
    private function log(Product $product, string $event, array $properties = []): void
    {
        activity('catalog')->event($event)->performedOn($product)
            ->withProperties([...$properties, 'label' => (string) $product->translate('ar', true)?->getAttribute('name')])
            ->log($event);
    }
}
