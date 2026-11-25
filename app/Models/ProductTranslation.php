<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedSlug;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class ProductTranslation extends Model
{
    use HasTranslatedSlug, RecordsActivity;

    public $timestamps = false;

    protected $fillable = ['name', 'slug', 'short_description', 'description', 'meta_title', 'meta_description'];

    public function activityParent(): ?array
    {
        return [Product::class, $this->attributes['product_id'] ?? null];
    }
}
