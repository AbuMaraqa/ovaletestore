<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedSlug;
use Illuminate\Database\Eloquent\Model;

class BrandTranslation extends Model
{
    use HasTranslatedSlug;

    public $timestamps = false;

    protected $fillable = ['name', 'slug', 'description'];
}
