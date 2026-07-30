<?php

namespace App\Services\Achievements;

use App\Models\ChecklistItem;
use App\Models\User;
use Carbon\Carbon;

/**
 * `achievements.criteria` JSON evaluated against a user's own logs
 * (FR-ACH-01). The Database Dictionary only sketches two example
 * shapes (`{"streak_days":30}`, `{"weight_loss_kg":5}`) without a
 * formal schema, so this is a designed extension covering both named
 * categories from the PRD ("streaks, milestones") with a `type`
 * discriminator key rather than guessing keys' meaning from context:
 *   - weight_loss_kg: total kg lost since the user's earliest weight
 *     log (falling back to health_profiles.initial_weight_kg when no
 *     log exists yet).
 *   - checklist_streak_days: N consecutive days (ending today) where
 *     every checklist item across the user's programs was checked.
 *   - program_milestone_days: the user has been on a program for at
 *     least N days since its start_date.
 */
class AchievementCriteriaEvaluator
{
    public function matches(User $user, array $criteria): bool
    {
        return match ($criteria['type'] ?? null) {
            'weight_loss_kg' => $this->weightLossKg($user, (float) $criteria['kg']),
            'checklist_streak_days' => $this->checklistStreakDays($user, (int) $criteria['days']),
            'program_milestone_days' => $this->programMilestoneDays($user, (int) $criteria['days']),
            default => false,
        };
    }

    private function weightLossKg(User $user, float $targetKg): bool
    {
        $initial = $user->weightLogs()->oldest('logged_at')->value('weight_kg')
            ?? $user->healthProfile?->initial_weight_kg;
        $current = $user->latestWeightKg();

        if ($initial === null || $current === null) {
            return false;
        }

        return ((float) $initial - $current) >= $targetKg;
    }

    private function checklistStreakDays(User $user, int $days): bool
    {
        $programIds = $user->programs()->pluck('id');

        if ($programIds->isEmpty()) {
            return false;
        }

        $date = Carbon::today();

        for ($i = 0; $i < $days; $i++) {
            $items = ChecklistItem::whereIn('user_program_id', $programIds)->whereDate('item_date', $date)->get();

            if ($items->isEmpty() || $items->contains('is_checked', false)) {
                return false;
            }

            $date = $date->copy()->subDay();
        }

        return true;
    }

    private function programMilestoneDays(User $user, int $days): bool
    {
        return $user->programs()->where('start_date', '<=', now()->subDays($days)->toDateString())->exists();
    }
}
