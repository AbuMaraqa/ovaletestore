<?php

namespace App\Models;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property int|null $source_id WooCommerce order id for imported history
 * @property OrderStatus $status
 * @property OrderChannel $channel
 * @property PaymentMethod $payment_method
 * @property int|null $company_id
 * @property int|null $sales_rep_id
 * @property int $subtotal
 * @property int $shipping_total
 * @property int $total
 * @property string $currency
 * @property string $exchange_rate units of `currency` per 1 base unit at checkout
 * @property int $base_total the total in the base currency (reports, balances)
 * @property Carbon $placed_at
 */
class Order extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'number', 'user_id', 'company_id', 'price_tier_id', 'sales_rep_id', 'status', 'channel', 'payment_method',
        'customer_name', 'customer_email', 'customer_phone',
        'shipping_city', 'shipping_address', 'shipping_method_id', 'shipping_method_name', 'customer_note', 'currency', 'exchange_rate', 'base_total',
        'subtotal', 'discount_total', 'shipping_total', 'total', 'locale', 'placed_at',
    ];

    protected $attributes = ['exchange_rate' => 1];

    /** Orders created without an explicit base amount (POS, imports, tests) derive it from the rate. */
    protected static function booted(): void
    {
        static::saving(function (Order $order): void {
            if ($order->isDirty('total') && ! $order->isDirty('base_total')) {
                $rate = (float) $order->exchange_rate;
                $order->base_total = $rate > 0 ? (int) round($order->total / $rate) : $order->total;
            }
        });
    }

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
            'base_total' => 'integer',
            'exchange_rate' => 'decimal:8',
            'placed_at' => 'datetime',
        ];
    }

    /** @return HasOne<Invoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
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

    protected function activityAttributes(): array
    {
        return [
            'status', 'customer_name', 'customer_email', 'customer_phone', 'shipping_city', 'shipping_address',
            'shipping_method_name', 'payment_method', 'customer_note', 'subtotal', 'discount_total', 'shipping_total', 'total',
        ];
    }
}
