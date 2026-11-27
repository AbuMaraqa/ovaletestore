<?php

namespace App\Tables;

use App\Models\User;
use App\Support\DataTable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A server-defined admin table (the project's "Inertia Table"): one class declares the query, the columns
 * (how each cell is drawn), the filters, sorting, row actions, bulk actions and the CSV export. The shared
 * Vue <ResourceTable> renders any of them, so every list in the admin works and looks the same.
 *
 * URL contract (App\Support\DataTable): filter[search], filter[<key>], filter[from|until], sort, per_page.
 *
 * Usage in a controller: `Inertia::render('Admin/Customers/Index', [...(new CustomersTable($request))->props('customers')])`
 * gives the page `customers` (the paginated rows), `state` and `table` (the definition).
 *
 * @template TModel of Model
 */
abstract class Table
{
    final public function __construct(protected readonly Request $request) {}

    /** Stable key: column preferences (browser) and the export URL. */
    abstract public static function key(): string;

    /** The permission needed to see (and export) this table. */
    abstract public static function permission(): string;

    abstract protected function title(): string;

    /** Route name of the list screen. */
    abstract protected function route(): string;

    /** @return Builder<TModel> */
    abstract protected function query(): Builder;

    /** @return list<Column> */
    abstract protected function columns(): array;

    /** @param TModel $model @return array<string, mixed> one row, as the page and the export see it */
    abstract protected function row(Model $model): array;

    /** Batch work for the rows about to be shown (e.g. one query for all links). @param Collection<int, TModel> $models */
    protected function prepare(Collection $models): void {}

    /** @return list<Filter> */
    protected function filters(): array
    {
        return [];
    }

    /** Filters without a panel control (e.g. links from a customer/company page: filter[customer]=5). @return list<AllowedFilter> */
    protected function hiddenFilters(): array
    {
        return [];
    }

    /** Search box: constrain the query for a term (null = no search box). @param Builder<TModel> $query */
    protected function search(Builder $query, string $term): void {}

    protected function searchPlaceholder(): ?string
    {
        return __('table.search');
    }

    protected function hasSearch(): bool
    {
        return true;
    }

    /** @return string|list<string> */
    protected function defaultSort(): string|array
    {
        return '-id';
    }

    /** Sorts that are not a plain column (e.g. custom AllowedSort). @return list<AllowedSort|string> */
    protected function extraSorts(): array
    {
        return [];
    }

    /**
     * Row actions: which row fields hold the URLs (present only when the user may do it).
     *
     * @return array{view?: string, edit?: string, delete?: string, label?: string, activity?: string, extra?: string}
     */
    protected function actions(): array
    {
        return ['view' => 'show_url', 'edit' => 'edit_url', 'delete' => 'delete_url', 'label' => 'name'];
    }

    /** @return list<BulkAction> */
    protected function bulkActions(): array
    {
        return [];
    }

    protected function exportable(): bool
    {
        return true;
    }

    protected function user(): User
    {
        /** @var User */
        return $this->request->user();
    }

    /**
     * Everything the page needs: the rows (under the given prop name), the URL state and the definition.
     *
     * @return array<string, mixed>
     */
    public function props(string $rowsKey): array
    {
        $page = $this->builder()->paginate(DataTable::perPage($this->request))->withQueryString();
        $this->prepare($page->getCollection());

        return [
            $rowsKey => $page->through(fn (Model $m) => $this->row($m)),
            'state' => DataTable::state($this->request),
            'table' => $this->definition(),
        ];
    }

    /**
     * Runs a bulk action on the selected ids, loaded through this table's query (never other rows). Each row
     * is handled on its own: a row that fails a business rule is skipped with its reason, the rest go through.
     *
     * @param  list<int|string>  $ids
     * @return array{done: int, skipped: list<string>}
     */
    public function runBulk(string $action, array $ids): array
    {
        $bulk = collect($this->bulkActions())->first(fn (BulkAction $b) => $b->name() === $action);
        abort_if($bulk === null, 404);
        abort_if($bulk->permission() !== null && ! $this->user()->can($bulk->permission()), 403);

        $done = 0;
        $skipped = [];
        foreach ($this->query()->whereKey($ids)->get() as $model) {
            try {
                $reason = $bulk->run($model);
            } catch (ValidationException $e) {
                $reason = collect($e->errors())->flatten()->first();
            } catch (AuthorizationException|HttpException) {
                $reason = __('table.bulk_not_allowed');
            }
            if ($reason === null) {
                $done++;
            } else {
                $skipped[] = $this->rowLabel($model).': '.$reason;
            }
        }

        return ['done' => $done, 'skipped' => $skipped];
    }

    /** Human label of a row in bulk results (the table's label field). */
    protected function rowLabel(Model $model): string
    {
        $field = $this->actions()['label'] ?? 'name';
        $value = $this->row($model)[$field] ?? null;

        return is_scalar($value) ? (string) $value : '#'.$model->getKey();
    }

    /** CSV (UTF-8 with BOM, so Excel shows Arabic correctly) of every row matching the current filters. */
    public function export(): StreamedResponse
    {
        abort_unless($this->exportable(), 404);
        $columns = $this->columns();
        $name = static::key().'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($columns): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(fn (Column $c) => $c->label(), $columns));
            $this->builder()->chunk(500, function (Collection $models) use ($out, $columns): void {
                $this->prepare($models);
                foreach ($models as $model) {
                    $row = $this->row($model);
                    fputcsv($out, array_map(fn (Column $c) => self::csvSafe($c->exportValue($row)), $columns));
                }
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return QueryBuilder<TModel> */
    private function builder(): QueryBuilder
    {
        $filters = [];
        if ($this->hasSearch()) {
            $filters[] = AllowedFilter::callback('search', function (Builder $q, $term): void {
                $term = trim((string) $term);
                if (mb_strlen($term) >= 1) {
                    $q->where(fn (Builder $w) => $this->search($w, $term));
                }
            });
        }
        foreach ($this->filters() as $filter) {
            array_push($filters, ...$filter->allowed());
        }
        array_push($filters, ...$this->hiddenFilters());
        // "Export selected": filter[ids]=1,2,3 (only ids this table lists anyway).
        $filters[] = AllowedFilter::callback('ids', fn (Builder $q, $ids) => $q->whereKey(array_slice(array_filter(array_map('intval', (array) $ids)), 0, 1000)));

        $sorts = array_values(array_filter(array_map(fn (Column $c) => $c->sortName(), $this->columns())));

        return QueryBuilder::for($this->query(), $this->request)
            ->allowedFilters(...$filters)
            ->allowedSorts(...[...$sorts, ...$this->extraSorts()])
            ->defaultSort(...(array) $this->defaultSort());
    }

    /** @return array<string, mixed> */
    private function definition(): array
    {
        $user = $this->user();
        $query = $this->request->query();

        return [
            'key' => static::key(),
            'title' => $this->title(),
            'url' => route($this->route()),
            'search' => $this->hasSearch() ? $this->searchPlaceholder() : null,
            'columns' => array_map(fn (Column $c) => $c->toArray(), $this->columns()),
            'filters' => array_map(fn (Filter $f) => $f->toArray(), $this->filters()),
            'actions' => $this->actions(),
            'bulk' => array_values(array_map(
                fn (BulkAction $b) => $b->toArray(),
                array_filter($this->bulkActions(), fn (BulkAction $b) => $b->permission() === null || $user->can($b->permission())),
            )),
            'bulk_url' => route('admin.tables.bulk', ['table' => static::key()]),
            'export_url' => $this->exportable()
                ? route('admin.tables.export', ['table' => static::key(), ...array_intersect_key($query, array_flip(['filter', 'sort']))])
                : null,
        ];
    }

    /** Cells starting with = + - @ would run as formulas in Excel. */
    private static function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 && ! is_numeric($value) ? "'".$value : $value;
    }
}
