<?php

namespace App\Services;

use App\Models\HealthProfile;
use App\Models\User;

class HealthProfileService
{
    /**
     * BMI/BMR (Mifflin-St Jeor)/TDEE per docs/08-Knowledge-Base.md §4.
     * Weight comes from the most recent body_measurements row, falling
     * back to health_profiles.initial_weight_kg - weight_logs (Phase 7)
     * will become the primary trigger once it exists.
     */
    private const ACTIVITY_MULTIPLIERS = [
        'sedentary' => 1.2,
        'light' => 1.375,
        'moderate' => 1.55,
        'heavy' => 1.725,
    ];

    public function recalculate(User $user): ?HealthProfile
    {
        $profile = $user->healthProfile;

        if (! $profile || ! $profile->height_cm || ! $profile->date_of_birth || ! $profile->gender) {
            return $profile;
        }

        $weightKg = $user->bodyMeasurements()->whereNotNull('weight_kg')->latest('measured_at')->value('weight_kg')
            ?? $profile->initial_weight_kg;

        if (! $weightKg) {
            return $profile;
        }

        $heightM = $profile->height_cm / 100;
        $bmi = round($weightKg / ($heightM ** 2), 2);

        $age = $profile->date_of_birth->age;
        $bmr = $profile->gender === 'male'
            ? 10 * $weightKg + 6.25 * $profile->height_cm - 5 * $age + 5
            : 10 * $weightKg + 6.25 * $profile->height_cm - 5 * $age - 161;

        $activityLevel = $user->lifestyleProfile?->activity_level ?? 'sedentary';
        $tdee = $bmr * self::ACTIVITY_MULTIPLIERS[$activityLevel];

        $profile->update([
            'bmi' => $bmi,
            'bmr' => round($bmr, 2),
            'tdee' => round($tdee, 2),
        ]);

        return $profile->fresh();
    }
}
