<?php

namespace App\Jobs;

use App\Models\UserProgram;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Scheduled daily. ProgramGenerationService::generateForDate() was only
 * ever wired to run on day 1 (onboarding completion) or via the member's
 * own "Buat Ulang Rencana Hari Ini" button - nothing generated day 2
 * onward automatically, so every active program went dark after its
 * first day. This finds every active program that doesn't already have
 * today's plan and dispatches GenerateProgramJob per program (not a
 * direct service call) so one member's AI failure can't abort the batch
 * and each generation still goes through the same status-tracking path
 * the manual button uses.
 */
class GenerateDailyProgramPlansJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $today = Carbon::today()->toDateString();

        UserProgram::where('status', 'active')
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today))
            ->whereDoesntHave('mealPlans', fn ($q) => $q->whereDate('plan_date', $today))
            ->chunkById(50, function ($userPrograms) use ($today) {
                foreach ($userPrograms as $userProgram) {
                    GenerateProgramJob::dispatch($userProgram, $today);
                }
            });
    }
}
