<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedSlug;
use Illuminate\Database\Eloquent\Model;

class CategoryTranslation extends Model
{
    use HasTranslatedSlug;

    public $timestamps = false;

    protected $fillable = ['name', 'slug', 'description', 'meta_title', 'meta_description'];
}
