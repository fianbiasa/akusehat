<?php

namespace App\Jobs;

use App\Models\OnboardingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Stub: the real pipeline (Goal -> RuleEngineService -> PromptBuilderService
 * -> AIGatewayService -> AIResponseProcessor -> persist plan) is Phases 4-6
 * of docs/11-Development-Checklist.md, none of which exist yet. This job
 * exists now so OnboardingCompleted has somewhere to dispatch to (FR-ONB-04)
 * without inventing a fake program-generation implementation ahead of the
 * Rule Engine / AI Provider layer it depends on.
 */
class GenerateInitialProgram implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public OnboardingSession $session) {}

    public function handle(): void
    {
        Log::info('GenerateInitialProgram dispatched, but the Rule Engine / AI Provider layer (Phases 4-6) is not implemented yet.', [
            'user_id' => $this->session->user_id,
            'onboarding_session_id' => $this->session->id,
        ]);
    }
}
