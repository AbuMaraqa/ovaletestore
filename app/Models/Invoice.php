<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property string $number
 * @property int $year
 * @property int $sequence
 * @property string $issued_on_status
 * @property Carbon $issued_at
 */
class Invoice extends Model
{
    protected $fillable = ['order_id', 'number', 'year', 'sequence', 'issued_on_status', 'issued_at'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'year' => 'integer', 'sequence' => 'integer'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public static function formatNumber(int $year, int $sequence): string
    {
        return sprintf('INV-%d-%05d', $year, $sequence);
    }
}
