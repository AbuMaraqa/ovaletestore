<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeTerm extends Model implements TranslatableContract
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatedAttributes = ['name'];

    protected $fillable = ['attribute_id', 'code', 'color_hex', 'sort_order'];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
