<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Tables\Tables;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * One endpoint for the bulk actions of every admin table: `{action, ids}`. The table checks the action's
 * permission and runs it row by row with the same rules as the single-row action; the result is flashed
 * ("done on N, skipped M: reasons").
 */
class TableBulkController extends Controller
{
    public function __invoke(Request $request, string $table): RedirectResponse
    {
        $class = Tables::find($table) ?? abort(404);
        $this->authorize($class::permission());
        $data = $request->validate([
            'action' => ['required', 'string', 'max:40'],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $result = (new $class($request))->runBulk($data['action'], array_map('intval', $data['ids']));

        $message = __('table.bulk_done', ['count' => $result['done']]);
        if ($result['skipped'] !== []) {
            $message .= ' '.__('table.bulk_skipped', ['count' => count($result['skipped'])]).' '.implode(' · ', array_slice($result['skipped'], 0, 5));
        }

        return back()->with($result['done'] > 0 ? 'success' : 'error', $message);
    }
}
