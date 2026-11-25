<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A B2B customer that sales reps order for from the POS. Orders are on account (debt).
 *
 * @property int $id
 * @property string $name
 * @property string|null $contact_name
 * @property string|null $phone
 * @property string|null $city
 * @property string|null $address
 * @property string|null $notes
 * @property bool $is_active
 * @property int|null $debit_total from withBalance()
 * @property int|null $paid_total from withBalance()
 */
class Company extends Model
{
    use RecordsActivity;

    protected $fillable = ['name', 'contact_name', 'phone', 'city', 'address', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CompanyPayment::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Adds debit_total (on-account, non-cancelled orders) and paid_total, in minor units. */
    public function scopeWithBalance(Builder $query): void
    {
        $query
            ->withSum(['orders as debit_total' => fn (Builder $q) => $q
                ->where('payment_method', PaymentMethod::OnAccount->value)
                ->where('status', '!=', OrderStatus::Cancelled->value)], 'base_total')
            ->withSum('payments as paid_total', 'amount');
    }

    /** Outstanding debt in minor units (negative = credit). Requires withBalance() or loads it. */
    public function balance(): int
    {
        if (! array_key_exists('debit_total', $this->attributes)) {
            $this->loadSum(['orders as debit_total' => fn ($q) => $q
                ->where('payment_method', PaymentMethod::OnAccount->value)
                ->where('status', '!=', OrderStatus::Cancelled->value)], 'base_total');
            $this->loadSum('payments as paid_total', 'amount');
        }

        return (int) $this->debit_total - (int) $this->paid_total;
    }
}
