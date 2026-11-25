<?php

namespace App\Support;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Which currency are we showing and charging in? Resolved once per request (scoped binding).
 *
 * Everything stored (prices, shipping, thresholds, balances) is in the base currency. The storefront
 * (ApplyStorefrontCurrency middleware) switches to the visitor's chosen currency; the admin, the POS,
 * mails and queue jobs stay in the base currency.
 */
final class CurrencyContext
{
    public const SESSION_KEY = 'currency';

    /** @var Collection<int, Currency>|null */
    private ?Collection $currencies = null;

    private ?Currency $current = null;

    /** @return Collection<int, Currency> active currencies, base first */
    public function active(): Collection
    {
        return $this->all()->filter(fn (Currency $c) => $c->is_active || $c->is_default)->values();
    }

    public function base(): Currency
    {
        return $this->all()->firstWhere('is_default', true) ?? $this->fallback();
    }

    public function current(): Currency
    {
        return $this->current ?? $this->base();
    }

    public function find(?string $code): ?Currency
    {
        return $code === null ? null : $this->active()->firstWhere('code', strtoupper($code));
    }

    /** Price in this currency for the rest of the request (unknown/inactive codes fall back to the base). */
    public function use(Currency|string|null $currency): self
    {
        $this->current = $currency instanceof Currency ? $currency : $this->find($currency);

        return $this;
    }

    public function isBase(): bool
    {
        return $this->current()->is_default;
    }

    /** Base amount → current currency (minor units). */
    public function convert(int $baseMinor): int
    {
        return $this->current()->fromBase($baseMinor);
    }

    /** Current-currency amount → base (minor units), e.g. a price filter typed by the visitor. */
    public function toBase(int $minor): int
    {
        return $this->current()->toBase($minor);
    }

    /** Formats a base amount in the current currency. */
    public function format(int $baseMinor, ?string $locale = null): string
    {
        return Money::format($this->convert($baseMinor), $this->current()->code, $locale);
    }

    /** Formats an amount that is already in the current currency. */
    public function formatConverted(int $minor, ?string $locale = null): string
    {
        return Money::format($minor, $this->current()->code, $locale);
    }

    /** Forget the loaded rows (after an admin edits currencies in the same request). */
    public function refresh(): void
    {
        $this->currencies = null;
        $this->current = null;
    }

    /** @return Collection<int, Currency> */
    private function all(): Collection
    {
        if ($this->currencies === null) {
            try {
                $this->currencies = Currency::query()->ordered()->get();
            } catch (Throwable) {
                $this->currencies = new Collection; // table not migrated yet (installs, early console commands)
            }
        }

        return $this->currencies;
    }

    private function fallback(): Currency
    {
        $currency = new Currency(['code' => 'ILS', 'name' => ['ar' => 'ILS'], 'rate' => 1]);
        $currency->is_default = true;

        return $currency;
    }
}
