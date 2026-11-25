<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Server half of the admin <DataTable>: one URL contract for every list screen —
 * `filter[search]`, `filter[<name>]`, `filter[from]` / `filter[until]`, `sort=-column`, `per_page`.
 * Filtering/sorting is whitelisted per screen with spatie/laravel-query-builder.
 */
final class DataTable
{
    public const PER_PAGE = [10, 25, 50, 100];

    public static function perPage(Request $request, int $default = 25): int
    {
        $value = (int) $request->query('per_page', (string) $default);

        return in_array($value, self::PER_PAGE, true) ? $value : $default;
    }

    /** What the table currently shows, echoed back so the UI renders the same filters/sort. */
    public static function state(Request $request): array
    {
        $filter = $request->query('filter');

        return [
            'filter' => is_array($filter) ? array_filter($filter, fn ($v) => is_scalar($v) && $v !== '') : (object) [],
            'sort' => is_string($request->query('sort')) ? $request->query('sort') : null,
            'per_page' => self::perPage($request),
        ];
    }

    /** LIKE pattern for user input (wildcards escaped). */
    public static function like(mixed $term): string
    {
        return '%'.addcslashes(trim((string) $term), '%_\\').'%';
    }

    /** `filter[from]` / `filter[until]` (Y-m-d) on a date column. @return list<AllowedFilter> */
    public static function dateRange(string $column): array
    {
        $date = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : null;

        return [
            AllowedFilter::callback('from', fn (Builder $q, $v) => $date($v) && $q->whereDate($column, '>=', $date($v))),
            AllowedFilter::callback('until', fn (Builder $q, $v) => $date($v) && $q->whereDate($column, '<=', $date($v))),
        ];
    }
}
