<?php

namespace App\Jobs;

use App\Models\DataExport;
use App\Notifications\DataExportReady;
use App\Services\UserDataExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Queued rather than synchronous - gathering everything across ~20
 * tables is not instant, and this is a "download when ready" flow
 * (notified async), not a blocking request/response like a photo view.
 */
class ExportUserDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public DataExport $export) {}

    public function handle(UserDataExportService $exportService): void
    {
        try {
            $data = $exportService->export($this->export->user);
            $path = "data-exports/{$this->export->user_id}/{$this->export->id}.json";

            Storage::disk('local')->put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->export->update(['file_path' => $path, 'status' => 'ready']);
        } catch (\Throwable $e) {
            $this->export->update(['status' => 'failed']);
            throw $e;
        }

        $this->export->user->notify(new DataExportReady($this->export));
    }
}
