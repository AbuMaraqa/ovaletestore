<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\AttachProductImages;
use App\Http\Controllers\Controller;
use App\Support\StagedUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One image per request, uploaded the moment it is picked on a product form (progress bar, per-file errors).
 * JSON in, JSON out (422 with the validation message for a rejected file). Attached when the form is saved.
 */
class StagedUploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['products.create', 'products.update']), 403);
        $request->validate(['file' => AttachProductImages::fileRules()]);

        return response()->json(['id' => StagedUploads::put($request->file('file'), $request->user())], 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        StagedUploads::forget($request->user(), $id);

        return response()->json(null, 204);
    }
}
