<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderNote extends Model
{
    use RecordsActivity;

    protected $fillable = ['order_id', 'user_id', 'body', 'is_customer_visible'];

    protected function casts(): array
    {
        return ['is_customer_visible' => 'boolean'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function activityParent(): ?array
    {
        return [Order::class, $this->attributes['order_id'] ?? null];
    }
}
