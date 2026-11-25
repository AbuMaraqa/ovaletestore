<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedSlug;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class CategoryTranslation extends Model
{
    use HasTranslatedSlug, RecordsActivity;

    public $timestamps = false;

    protected $fillable = ['name', 'slug', 'description', 'meta_title', 'meta_description'];

    public function activityParent(): ?array
    {
        return [Category::class, $this->attributes['category_id'] ?? null];
    }
}
