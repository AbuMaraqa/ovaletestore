<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedSlug;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class BrandTranslation extends Model
{
    use HasTranslatedSlug, RecordsActivity;

    public $timestamps = false;

    protected $fillable = ['name', 'slug', 'description'];

    public function activityParent(): ?array
    {
        return [Brand::class, $this->attributes['brand_id'] ?? null];
    }
}
