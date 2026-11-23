<?php

namespace App\Models;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $number
 * @property OrderStatus $status
 * @property OrderChannel $channel
 * @property PaymentMethod $payment_method
 * @property int|null $company_id
 * @property int|null $sales_rep_id
 * @property int $subtotal
 * @property int $shipping_total
 * @property int $total
 * @property string $currency
 * @property Carbon $placed_at
 */
class Order extends Model
{
    use LogsActivity;

    protected $fillable = [
        'number', 'user_id', 'company_id', 'sales_rep_id', 'status', 'channel', 'payment_method',
        'customer_name', 'customer_email', 'customer_phone',
        'shipping_city', 'shipping_address', 'shipping_method_id', 'shipping_method_name', 'customer_note', 'currency',
        'subtotal', 'discount_total', 'shipping_total', 'total', 'locale', 'placed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'channel' => OrderChannel::class,
            'payment_method' => PaymentMethod::class,
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'shipping_total' => 'integer',
            'total' => 'integer',
            'placed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OrderNote::class)->latest();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status'])->logOnlyDirty();
    }
}
