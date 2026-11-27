<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ActivityResource;
use App\Models\User;
use App\Support\ActivityLog;
use App\Tables\Admin\ActivityTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/** The audit trail: everything staff (and the system) changed, who did it, when and from where. */
class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('activity.view');

        return Inertia::render('Admin/Activity/Index', (new ActivityTable($request))->props('activities'));
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
        ActivityLog::attachUrls($activities);

        return response()->json(['data' => ActivityResource::collection($activities)->resolve()]);
    }

    /**
     * Entries with their author (and the author's avatar media) in two queries, not one per row.
     *
     * @return Builder<Activity>
     */
    private function entries(): Builder
    {
        return Activity::query()->with(['causer' => fn (MorphTo $m) => $m->morphWith([User::class => ['media']])]);
    }
}
