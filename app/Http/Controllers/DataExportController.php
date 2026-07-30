<?php

namespace App\Http\Controllers;

use App\Jobs\ExportUserDataJob;
use App\Models\DataExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $export = $request->user()->dataExports()->create(['status' => 'pending', 'created_at' => now()]);

        ExportUserDataJob::dispatch($export);

        return back();
    }

    /**
     * Unlike progress photos' signed show() route, this also requires
     * an authenticated session matching the export's owner - a full
     * personal-data export is more sensitive than a single photo, so a
     * leaked email link alone isn't sufficient (defense in depth on
     * top of the signature).
     */
    public function download(Request $request, DataExport $export): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless($export->user_id === $request->user()->id, 403);
        abort_unless($export->status === 'ready' && $export->file_path, 404);

        return Storage::disk('local')->download($export->file_path, "akusehat-data-export-{$export->id}.json");
    }
}
