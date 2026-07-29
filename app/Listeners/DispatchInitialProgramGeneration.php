<?php

namespace App\Listeners;

use App\Events\OnboardingCompleted;
use App\Jobs\GenerateInitialProgram;

class DispatchInitialProgramGeneration
{
    public function handle(OnboardingCompleted $event): void
    {
        GenerateInitialProgram::dispatch($event->session);
    }
}
