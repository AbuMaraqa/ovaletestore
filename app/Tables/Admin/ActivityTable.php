<?php

namespace App\Tables\Admin;

use App\Http\Resources\Admin\ActivityResource;
use App\Models\User;
use App\Support\ActivityLog;
use App\Support\DataTable;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Spatie\Activitylog\Models\Activity;

/**
 * The audit trail, limited to the channels the viewer may read (ActivityLog::visibleLogs). Read-only:
 * no bulk changes (an audit trail is never edited); export of the visible entries only.
 *
 * @extends Table<Activity>
 */
class ActivityTable extends Table
{
    public static function key(): string
    {
        return 'activity';
    }

    public static function permission(): string
    {
        return 'activity.view';
    }

    protected function title(): string
    {
        return __('activity.title');
    }

    protected function route(): string
    {
        return 'admin.activity.index';
    }

    protected function query(): Builder
    {
        return Activity::query()
            ->with(['causer' => fn (MorphTo $m) => $m->morphWith([User::class => ['media']])])
            ->whereIn('log_name', ActivityLog::visibleLogs($this->user()));
    }

    protected function search(Builder $query, string $term): void
    {
        if (mb_strlen($term) < 2) {
            return;
        }
        $like = DataTable::like($term);
        $query->where('properties->label', 'like', $like)
            ->orWhere('description', 'like', $like)
            ->orWhereHasMorph('causer', [User::class], fn (Builder $c) => $c->where('name', 'like', $like));
    }

    private function label(string $group, string $key): string
    {
        return Lang::has("activity.{$group}.{$key}") ? __("activity.{$group}.{$key}") : $key;
    }

    protected function columns(): array
    {
        return [
            Column::text('event', __('activity.event'))->exportAs(fn (array $r) => $this->label('events', (string) $r['event']))->fixed(),
            Column::text('record', __('activity.record'))->exportAs(fn (array $r) => trim(($r['subject'] ? $this->label('subjects', (string) $r['subject']).': ' : '').($r['label'] ?? ''))),
            Column::text('causer', __('activity.user'))->exportAs(fn (array $r) => $r['causer']['name'] ?? __('activity.system')),
            Column::text('changes', __('activity.changes'))->exportAs(fn (array $r) => collect($r['changes'])->map(fn ($c) => ($c['label'] ?? $c['field'] ?? '').': '.($c['old'] ?? '—').' → '.($c['new'] ?? '—'))->implode(' | ')),
            Column::text('log', __('activity.log'))->exportAs(fn (array $r) => $this->label('logs', (string) ($r['log'] ?? 'default')))->hidden(),
            Column::text('ip', __('activity.ip'))->ltr()->mono()->hidden(),
            Column::date('created_at', __('activity.date'))->field('ago')->title('date')->exportAs(fn (array $r) => (string) $r['date'])->sortable()->align('end'),
        ];
    }

    protected function filters(): array
    {
        $visible = ActivityLog::visibleLogs($this->user());
        $subjects = collect(array_keys(ActivityLog::SUBJECTS))->reject(fn ($s) => str_ends_with($s, '_translation'))
            ->mapWithKeys(fn ($s) => [$s => $this->label('subjects', $s)])->all();
        $events = Cache::remember('activity:events', 600, fn () => Activity::query()->distinct()->orderBy('event')->pluck('event')->filter()->values()->all());
        $causers = Cache::remember('activity:causers', 600, fn () => User::query()
            ->whereIn('id', Activity::query()->where('causer_type', (new User)->getMorphClass())->distinct()->select('causer_id'))
            ->orderBy('name')->pluck('name', 'id')->all());

        return [
            Filter::select('log', __('activity.log'), collect(array_intersect(ActivityLog::LOGS, $visible))->mapWithKeys(fn ($l) => [$l => $this->label('logs', $l)])->all(),
                fn (Builder $q, string $v) => $q->where('log_name', $v)),
            Filter::select('event', __('activity.event'), collect($events)->mapWithKeys(fn ($e) => [$e => $this->label('events', (string) $e)])->all(),
                fn (Builder $q, string $v) => $q->where('event', $v)),
            Filter::select('subject', __('activity.record'), $subjects, fn (Builder $q, string $key) => $q->where(fn (Builder $w) => $w
                ->where('subject_type', ActivityLog::modelFor($key) ?? '-')
                ->orWhere('parent_type', ActivityLog::modelFor($key) ?? '-'))),
            Filter::select('causer', __('activity.user'), $causers, fn (Builder $q, string $v) => $q->where('causer_id', (int) $v)),
            Filter::dateRange(__('activity.date'), 'created_at'),
        ];
    }

    protected function extraSorts(): array
    {
        return ['id'];
    }

    protected function actions(): array
    {
        return ['label' => 'label'];
    }

    protected function prepare(Collection $models): void
    {
        ActivityLog::attachUrls($models);
    }

    /** @param Activity $activity */
    protected function row(Model $activity): array
    {
        return (new ActivityResource($activity))->resolve($this->request);
    }
}
