<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class ShippingMethodTranslation extends Model
{
    use RecordsActivity;

    public $timestamps = false;

    protected $fillable = ['name', 'description'];

    public function activityParent(): ?array
    {
        return [ShippingMethod::class, $this->attributes['shipping_method_id'] ?? null];
    }
}
