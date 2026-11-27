<?php

namespace App\Tables\Admin;

use App\Enums\AttributeDisplayType;
use App\Models\Attribute;
use App\Support\DataTable;
use App\Tables\BulkAction;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;

/**
 * Global attributes (size, flavour…) and their terms.
 *
 * @extends Table<Attribute>
 */
class AttributesTable extends Table
{
    public static function key(): string
    {
        return 'attributes';
    }

    public static function permission(): string
    {
        return 'attributes.view';
    }

    protected function title(): string
    {
        return __('admin.attributes');
    }

    protected function route(): string
    {
        return 'admin.attributes.index';
    }

    protected function query(): Builder
    {
        return Attribute::query()->global()->with(['translations', 'terms.translations'])->withCount('terms');
    }

    protected function search(Builder $query, string $term): void
    {
        $like = DataTable::like($term);
        $query->where('code', 'like', $like)->orWhereHas('translations', fn ($t) => $t->where('name', 'like', $like));
    }

    protected function searchPlaceholder(): string
    {
        return __('admin.search_attributes');
    }

    protected function defaultSort(): string
    {
        return 'sort_order';
    }

    /** @return array<string, string> */
    private function displayTypes(): array
    {
        return collect(AttributeDisplayType::cases())->mapWithKeys(fn ($d) => [
            $d->value => Lang::has("admin.display_{$d->value}") ? __("admin.display_{$d->value}") : $d->value,
        ])->all();
    }

    protected function columns(): array
    {
        return [
            Column::text('name', __('admin.name'))->link('edit_url')->sub('code')->fixed(),
            Column::text('code', __('admin.code'))->ltr()->mono()->muted()->sortable()->hidden(),
            Column::badge('display_type', __('admin.type'), collect($this->displayTypes())->map(fn ($l) => [$l, 'secondary'])->all())->hidden(),
            Column::text('terms', __('admin.terms'))->field('terms_preview')->muted()->truncate()->title('terms_all'),
            Column::number('terms_count', __('admin.terms_count'))->sortable(),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('display', __('admin.type'), $this->displayTypes(), fn (Builder $q, string $v) => $q->where('display_type', $v)),
        ];
    }

    protected function extraSorts(): array
    {
        return ['sort_order', 'id'];
    }

    protected function actions(): array
    {
        return ['edit' => 'edit_url', 'delete' => 'delete_url', 'label' => 'name', 'activity' => 'attribute'];
    }

    protected function bulkActions(): array
    {
        return [
            BulkAction::make('delete', __('table.delete_selected'), 'attributes.delete', function (Model $attribute): ?string {
                $attribute->delete();

                return null;
            })->destructive(),
        ];
    }

    /** @param Attribute $a */
    protected function row(Model $a): array
    {
        $user = $this->user();
        $terms = $a->terms->pluck('name');

        return [
            'id' => $a->id,
            'name' => $a->name,
            'code' => $a->code,
            'display_type' => $a->display_type->value,
            'terms' => $terms,
            'terms_preview' => $terms->take(8)->implode('، ').($terms->count() > 8 ? ' +'.($terms->count() - 8) : ''),
            'terms_all' => $terms->implode('، '),
            'terms_count' => $a->terms_count,
            'edit_url' => $user->can('attributes.update') ? route('admin.attributes.edit', $a) : null,
            'delete_url' => $user->can('attributes.delete') ? route('admin.attributes.destroy', $a) : null,
        ];
    }
}
