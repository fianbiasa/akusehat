<?php

namespace App\Jobs;

use App\Models\Achievement;
use App\Models\User;
use App\Models\UserAchievement;
use App\Notifications\AchievementEarned;
use App\Services\Achievements\AchievementCriteriaEvaluator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Scheduled daily (Phase 10 checklist). Checks every onboarded user
 * against every achievement they haven't already earned - the
 * `uniq(user_id, achievement_id)` constraint means this is naturally
 * idempotent even if the job ever runs twice for the same day.
 */
class EvaluateAchievementsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AchievementCriteriaEvaluator $evaluator): void
    {
        $achievements = Achievement::all();

        if ($achievements->isEmpty()) {
            return;
        }

        User::whereNotNull('onboarding_completed_at')->chunkById(50, function ($users) use ($achievements, $evaluator) {
            foreach ($users as $user) {
                $earnedIds = $user->userAchievements()->pluck('achievement_id');

                foreach ($achievements->whereNotIn('id', $earnedIds) as $achievement) {
                    if ($evaluator->matches($user, $achievement->criteria)) {
                        UserAchievement::create([
                            'user_id' => $user->id,
                            'achievement_id' => $achievement->id,
                            'earned_at' => now(),
                        ]);

                        $user->notify(new AchievementEarned($achievement));
                    }
                }
            }
        });
    }
}
