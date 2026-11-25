<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class AttributeTranslation extends Model
{
    use RecordsActivity;

    public $timestamps = false;

    protected $fillable = ['name'];

    public function activityParent(): ?array
    {
        return [Attribute::class, $this->attributes['attribute_id'] ?? null];
    }
}
