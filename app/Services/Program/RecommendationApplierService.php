<?php

namespace App\Services\Program;

use App\Events\AIRecommendationCreated;
use App\Models\AiRecommendation;
use App\Models\UserProgram;

/**
 * Applies (or queues for Coach approval) the `adjustments[]` produced by
 * the weeklyReview() AI capability (FR-PROG-04).
 *
 * The AI's `auto_applicable` flag is advisory - docs/06-AI-Provider-
 * Interface.md §4.2 is explicit that "the authoritative decision on
 * whether it's within bounds still runs through RuleEngineService server-
 * side". But `adjustments[].detail` is free text ("increase push-up reps
 * from 10 to 20"), not a structured delta - there is no sound way to
 * parse that into a bounded, machine-verifiable mutation of
 * workout_plan_items/meal_plan_items. Rather than fabricate an unsound
 * text-parsing heuristic that pretends to bounds-check something it
 * cannot actually verify, this only auto-applies adjustment types that
 * never mutate structured plan data (habit/motivation) - meal_adjustment
 * and workout_adjustment always route to Coach approval. This is a
 * conservative, documented interpretation of "bounds-check before
 * auto-applying" given the actual data shape available; see
 * docs/11-Development-Checklist.md Phase 6 notes.
 */
class RecommendationApplierService
{
    private const AUTO_APPLICABLE_TYPES = ['habit', 'motivation'];

    private const KNOWN_TYPES = ['meal_adjustment', 'workout_adjustment', 'habit', 'motivation', 'alert'];

    public function applyAdjustments(UserProgram $userProgram, array $adjustments): void
    {
        foreach ($adjustments as $adjustment) {
            $this->applyOne($userProgram, $adjustment);
        }
    }

    private function applyOne(UserProgram $userProgram, array $adjustment): AiRecommendation
    {
        $type = in_array($adjustment['type'] ?? null, self::KNOWN_TYPES, true) ? $adjustment['type'] : 'habit';
        $canAutoApply = ($adjustment['auto_applicable'] ?? false) && in_array($type, self::AUTO_APPLICABLE_TYPES, true);

        $recommendation = AiRecommendation::create([
            'user_id' => $userProgram->user_id,
            'user_program_id' => $userProgram->id,
            'type' => $type,
            'content' => $adjustment,
            'rationale' => $adjustment['detail'] ?? null,
            'status' => $canAutoApply ? 'applied' : 'pending',
            'applied_at' => $canAutoApply ? now() : null,
        ]);

        if (! $canAutoApply) {
            AIRecommendationCreated::dispatch($recommendation);
        }

        return $recommendation;
    }
}
