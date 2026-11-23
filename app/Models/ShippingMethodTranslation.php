<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingMethodTranslation extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'description'];
}
