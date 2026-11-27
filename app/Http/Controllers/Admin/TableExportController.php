<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Tables\Tables;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV export of any admin table, with the same filters/sort the list shows and the same permission. */
class TableExportController extends Controller
{
    public function __invoke(Request $request, string $table): StreamedResponse
    {
        $class = Tables::find($table) ?? abort(404);
        $this->authorize($class::permission());

        return (new $class($request))->export();
    }
}
