<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ActivityResource;
use App\Models\User;
use App\Support\ActivityLog;
use App\Support\DataTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/** The audit trail: everything staff (and the system) changed, who did it, when and from where. */
class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('activity.view');

        $visible = ActivityLog::visibleLogs($request->user());

        $activities = QueryBuilder::for($this->entries()->whereIn('log_name', $visible))
            ->allowedFilters(...[
                AllowedFilter::callback('search', function (Builder $q, $term) {
                    if (mb_strlen(trim((string) $term)) < 2) {
                        return;
                    }
                    $like = DataTable::like($term);
                    $q->where(fn (Builder $w) => $w->where('properties->label', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhereHasMorph('causer', [User::class], fn (Builder $c) => $c->where('name', 'like', $like)));
                }),
                AllowedFilter::exact('log', 'log_name'),
                AllowedFilter::exact('event'),
                AllowedFilter::exact('causer', 'causer_id'),
                AllowedFilter::callback('subject', fn (Builder $q, $key) => $q->where(fn (Builder $w) => $w
                    ->where('subject_type', ActivityLog::modelFor((string) $key) ?? '-')
                    ->orWhere('parent_type', ActivityLog::modelFor((string) $key) ?? '-'))),
                ...DataTable::dateRange('created_at'),
            ])
            ->allowedSorts(...['id', 'created_at'])
            ->defaultSort('-id')
            ->paginate(DataTable::perPage($request))
            ->withQueryString();

        $this->attachUrls($activities->getCollection());

        return Inertia::render('Admin/Activity/Index', [
            'activities' => ActivityResource::collection($activities),
            'state' => DataTable::state($request),
            'logs' => array_values(array_intersect(ActivityLog::LOGS, $visible)),
            // Filter option lists change rarely: cached instead of scanning the log on every visit.
            'events' => Cache::remember('activity:events', 600, fn () => Activity::query()->distinct()->orderBy('event')->pluck('event')->filter()->values()->all()),
            'subjects' => array_keys(ActivityLog::SUBJECTS),
            'causers' => Cache::remember('activity:causers', 600, fn () => User::query()
                ->whereIn('id', Activity::query()->where('causer_type', (new User)->getMorphClass())->distinct()->select('causer_id'))
                ->orderBy('name')->get(['id', 'name'])->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name])->all()),
        ]);
    }

    /** History of one record (and its child rows) for the "activity" side panel. */
    public function record(Request $request, string $type, int $id): JsonResponse
    {
        $this->authorize('activity.view');
        $class = ActivityLog::modelFor($type);
        abort_unless($class !== null, 404);
        $visible = ActivityLog::visibleLogs($request->user());
        abort_unless(in_array(ActivityLog::SUBJECTS[$type]['log'], $visible, true), 403);

        $activities = ActivityLog::forRecord($this->entries(), $class, $id)
            ->whereIn('log_name', $visible)->latest('id')->limit(200)->get();
        $this->attachUrls($activities);

        return response()->json(['data' => ActivityResource::collection($activities)->resolve()]);
    }

    /** Entries with their author (and the author's avatar media) in two queries, not one per row. */
    private function entries(): Builder
    {
        return Activity::query()->with(['causer' => fn (MorphTo $m) => $m->morphWith([User::class => ['media']])]);
    }

    /** Link each entry to its record's admin page (one existence query per subject type, not per row). */
    private function attachUrls(Collection $activities): void
    {
        $refs = $activities->map(fn (Activity $a) => [
            'class' => $a->getAttribute('parent_type') ?? $a->subject_type,
            'id' => $a->getAttribute('parent_id') ?? $a->subject_id,
        ]);

        $existing = $refs->filter(fn ($r) => $r['class'] && $r['id'] && isset(ActivityLog::SUBJECTS[ActivityLog::keyFor($r['class']) ?? '']['route']))
            ->groupBy('class')
            ->map(fn (Collection $group, string $class) => $class::query()->whereKey($group->pluck('id')->unique())->pluck((new $class)->getKeyName())->flip());

        $activities->each(function (Activity $activity, int $i) use ($refs, $existing) {
            ['class' => $class, 'id' => $id] = $refs[$i];
            $route = ActivityLog::SUBJECTS[ActivityLog::keyFor($class) ?? '']['route'] ?? null;
            $activity->setAttribute('url', $route && isset($existing[$class][$id]) ? route($route, $id) : null);
        });
    }
}
