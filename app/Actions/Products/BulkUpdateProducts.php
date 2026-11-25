<?php

namespace App\Actions\Products;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/** List bulk actions: publish / draft / archive / delete (soft, like the single delete). Returns how many changed. */
class BulkUpdateProducts
{
    public const ACTIONS = ['publish', 'draft', 'archive', 'delete'];

    /** @param list<int> $ids */
    public function handle(string $action, array $ids): int
    {
        $status = ['publish' => ProductStatus::Published, 'draft' => ProductStatus::Draft, 'archive' => ProductStatus::Archived][$action] ?? null;
        $products = Product::query()->whereKey($ids)->get();

        DB::transaction(function () use ($products, $status) {
            foreach ($products as $product) {
                if ($status === null) {
                    $product->delete();

                    continue;
                }
                $product->update([
                    'status' => $status,
                    'published_at' => $status === ProductStatus::Published ? ($product->published_at ?? now()) : $product->published_at,
                ]);
            }
        });

        return $products->count();
    }
}
