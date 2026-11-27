<?php

namespace App\Tables;

use App\Support\DataTable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * A table filter: what the filter panel shows (select options or a date range) and how the server
 * applies it (`filter[<key>]` / `filter[from]` + `filter[until]`, whitelisted with laravel-query-builder).
 */
final class Filter
{
    /** @var list<AllowedFilter> */
    private array $allowed;

    /** @param array<string, mixed> $spec */
    private function __construct(private readonly array $spec, AllowedFilter ...$allowed)
    {
        $this->allowed = array_values($allowed);
    }

    /**
     * @param  array<string|int, string>  $options  value => label
     * @param  Closure(Builder<Model>, string): mixed  $apply
     */
    public static function select(string $key, string $label, array $options, Closure $apply): self
    {
        $values = array_map('strval', array_keys($options));

        return new self(
            ['key' => $key, 'label' => $label, 'type' => 'select', 'options' => collect($options)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all()],
            // Unknown values are ignored instead of reaching the query.
            AllowedFilter::callback($key, fn (Builder $q, $value) => in_array((string) $value, $values, true) ? $apply($q, (string) $value) : null),
        );
    }

    /** `filter[from]` / `filter[until]` on a date column (one date range per table). */
    public static function dateRange(string $label, string $column): self
    {
        return new self(['key' => 'date', 'label' => $label, 'type' => 'date_range'], ...DataTable::dateRange($column));
    }

    /** @return list<AllowedFilter> */
    public function allowed(): array
    {
        return $this->allowed;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->spec;
    }
}
