<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Program\AIMemoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Scheduled daily (04-Architecture.md §6) - scans every user with at
 * least one active program for trends/patterns/milestones/concerns.
 */
class ScanAIMemoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AIMemoryService $service): void
    {
        User::whereHas('activePrograms')->chunkById(50, function ($users) use ($service) {
            foreach ($users as $user) {
                $service->scan($user);
            }
        });
    }
}
