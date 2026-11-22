<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** An attribute assigned to a product, with the subset of terms the product offers. */
class ProductAttribute extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'attribute_id', 'used_for_variations', 'is_visible', 'sort_order'];

    protected function casts(): array
    {
        return ['used_for_variations' => 'boolean', 'is_visible' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(AttributeTerm::class, 'product_attribute_term')
            ->orderBy('attribute_terms.sort_order')
            ->orderBy('attribute_terms.id');
    }
}
