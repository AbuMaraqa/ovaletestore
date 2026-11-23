<?php

namespace App\Models;

use App\Enums\CompanyPaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money received from a company against its account.
 *
 * @property int $id
 * @property int $company_id
 * @property int $amount minor units
 * @property CompanyPaymentMethod $method
 * @property string|null $reference
 * @property string|null $note
 * @property Carbon $paid_at
 * @property int|null $recorded_by
 */
class CompanyPayment extends Model
{
    protected $fillable = ['company_id', 'amount', 'method', 'reference', 'note', 'paid_at', 'recorded_by'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'method' => CompanyPaymentMethod::class, 'paid_at' => 'date'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
