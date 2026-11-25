<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class AttributeTermTranslation extends Model
{
    use RecordsActivity;

    public $timestamps = false;

    protected $fillable = ['name'];

    /** Term edits belong to the attribute's history. */
    public function activityParent(): ?array
    {
        return [Attribute::class, AttributeTerm::query()->whereKey($this->attributes['attribute_term_id'] ?? null)->value('attribute_id')];
    }
}
