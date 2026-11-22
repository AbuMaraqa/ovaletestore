<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedSlug;
use Illuminate\Database\Eloquent\Model;

class ProductTranslation extends Model
{
    use HasTranslatedSlug;

    public $timestamps = false;

    protected $fillable = ['name', 'slug', 'short_description', 'description', 'meta_title', 'meta_description'];
}
