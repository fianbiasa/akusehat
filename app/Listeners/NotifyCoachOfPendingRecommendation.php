<?php

namespace App\Listeners;

use App\Events\AIRecommendationCreated;
use App\Notifications\RecommendationPendingApproval;

/**
 * coach_members (Phase 8) doesn't exist yet, but user_programs.coach_id
 * already does - this safely no-ops until a program has a coach assigned.
 */
class NotifyCoachOfPendingRecommendation
{
    public function handle(AIRecommendationCreated $event): void
    {
        $coach = $event->recommendation->userProgram?->coach;

        if ($coach) {
            $coach->notify(new RecommendationPendingApproval($event->recommendation));
        }
    }
}
