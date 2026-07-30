<?php

namespace App\Jobs;

use App\Models\AiMemory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Scheduled weekly (04-Architecture.md §6) - older memories matter less
 * for prompt context than recent ones, so relevance_score decays over
 * time rather than memories being deleted outright (still useful for
 * Admin audit/history).
 */
class PruneAIMemoryRelevanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const DECAY_FACTOR = 0.9;

    private const MIN_AGE_DAYS = 30;

    public function handle(): void
    {
        AiMemory::where('created_at', '<=', now()->subDays(self::MIN_AGE_DAYS))
            ->update(['relevance_score' => DB::raw('GREATEST(relevance_score * '.self::DECAY_FACTOR.', 0.1)')]);
    }
}
