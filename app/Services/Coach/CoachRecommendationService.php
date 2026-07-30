<?php

namespace App\Services\Coach;

use App\Models\AiRecommendation;
use App\Models\User;
use App\Notifications\RecommendationReviewed;

/**
 * Coach approve/reject of a *pending* ai_recommendation
 * (RecommendationApplierService, Phase 6, already decided this one
 * couldn't auto-apply). Approving here is an audit/transparency action
 * matching wireframe/coach.md - "surfaces to the Member as a 'Program
 * updated' note" - not a structural plan mutation, for the same reason
 * Phase 6 never auto-applied meal/workout adjustments: the AI's
 * `detail` field is free text, not a machine-actionable delta.
 */
class CoachRecommendationService
{
    public function approve(AiRecommendation $recommendation, User $coach): AiRecommendation
    {
        $recommendation->update([
            'status' => 'applied',
            'applied_at' => now(),
            'reviewed_by' => $coach->id,
        ]);

        $recommendation = $recommendation->fresh();
        $recommendation->user->notify(new RecommendationReviewed($recommendation));

        return $recommendation;
    }

    public function reject(AiRecommendation $recommendation, User $coach, ?string $reason = null): AiRecommendation
    {
        $recommendation->update([
            'status' => 'rejected',
            'reviewed_by' => $coach->id,
            'content' => [...$recommendation->content, 'rejection_reason' => $reason],
        ]);

        $recommendation = $recommendation->fresh();
        $recommendation->user->notify(new RecommendationReviewed($recommendation));

        return $recommendation;
    }
}
