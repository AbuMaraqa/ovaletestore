<?php

namespace App\Actions\Currencies;

use App\Models\Currency;
use App\Queries\PosCatalog;
use App\Settings\StoreSettings;
use App\Support\CurrencyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Makes another currency the base (default) one. Every amount stored in the old base currency is
 * converted at the new currency's rate, in one transaction:
 *   variant regular/sale prices, trader tier prices, shipping costs and thresholds, the minimum order,
 *   company payments and every order's `base_total` (so reports and company balances stay consistent).
 * Each order keeps its own charged currency and totals untouched — that is what the customer paid;
 * its `exchange_rate` and `base_total` are re-expressed against the new base (exactly `total` when the
 * order was paid in the new base currency). The other currencies' rates are re-expressed too.
 *
 * Both rows are re-read under a lock inside the transaction, so a double click or two tabs can never
 * convert the prices twice.
 */
class SetBaseCurrency
{
    /** table => money columns stored in the base currency */
    public const COLUMNS = [
        'product_variants' => ['regular_price', 'sale_price'],
        'price_tier_prices' => ['price'],
        'shipping_methods' => ['cost', 'free_over', 'min_order'],
        'company_payments' => ['amount'],
    ];

    public function __construct(private readonly StoreSettings $store, private readonly CurrencyContext $currencies) {}

    public function handle(Currency $currency): void
    {
        DB::transaction(function () use ($currency): void {
            $old = Currency::query()->where('is_default', true)->lockForUpdate()->firstOrFail();
            $currency = Currency::query()->lockForUpdate()->findOrFail($currency->id);
            if ($currency->is_default || $old->is($currency)) {
                return; // already the base (e.g. the second of two clicks)
            }
            if (! $currency->is_active || (float) $currency->rate <= 0) {
                throw ValidationException::withMessages(['currency' => __('currencies.cannot_be_default')]);
            }
            $factor = (float) $currency->rate; // new-currency units per 1 old base unit

            foreach (self::COLUMNS as $table => $columns) {
                $updates = [];
                foreach ($columns as $column) {
                    $updates[$column] = DB::raw($this->convertSql($column, $factor));
                }
                DB::table($table)->update($updates);
            }

            $code = DB::getPdo()->quote($currency->code);
            DB::table('orders')->update([
                'base_total' => DB::raw("CASE WHEN currency = {$code} THEN total ELSE {$this->convertSql('base_total', $factor)} END"),
                'exchange_rate' => DB::raw(sprintf('ROUND(exchange_rate / %.8F, 8)', $factor)),
            ]);

            $this->store->min_order_total = (int) round($this->store->min_order_total * $factor);
            $this->store->save();

            // Re-express every rate against the new base (the old base becomes 1 / factor).
            activity()->withoutLogging(function () use ($currency, $factor): void {
                foreach (Currency::query()->lockForUpdate()->get() as $row) {
                    $row->is_default = $row->is($currency);
                    $row->rate = $row->is($currency) ? '1' : sprintf('%.8F', (float) $row->rate / $factor);
                    $row->save();
                }
            });

            activity('settings')
                ->performedOn($currency)
                ->event('base_currency_changed')
                ->withProperties(['from' => $old->code, 'to' => $currency->code, 'rate' => sprintf('%.8F', $factor)])
                ->log('base_currency_changed');
        });

        $this->currencies->refresh();
        PosCatalog::forget();
    }

    /** `ROUND(column * factor)` as an integer, NULLs kept (portable across SQLite and MySQL). */
    private function convertSql(string $column, float $factor): string
    {
        $cast = DB::getDriverName() === 'sqlite' ? 'INTEGER' : 'UNSIGNED';

        return sprintf('CAST(ROUND(%s * %.8F) AS %s)', $column, $factor, $cast);
    }
}
