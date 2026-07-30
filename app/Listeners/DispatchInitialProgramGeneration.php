<?php

namespace App\Listeners;

use App\Events\OnboardingCompleted;
use App\Jobs\GenerateProgramJob;
use App\Services\Program\ProgramGenerationService;
use Illuminate\Support\Carbon;

/**
 * The Rule Engine / AI Provider layer this depended on (Phases 4-5) now
 * exists, so this creates the real UserProgram/ProgramGoal/Reminders
 * (ProgramGenerationService::bootstrap) and queues today's plan
 * generation - no longer the Phase 2 logging stub.
 */
class DispatchInitialProgramGeneration
{
    public function __construct(private ProgramGenerationService $programGenerationService) {}

    public function handle(OnboardingCompleted $event): void
    {
        $userProgram = $this->programGenerationService->bootstrap($event->session);

        GenerateProgramJob::dispatch($userProgram, Carbon::today()->toDateString());
    }
}
