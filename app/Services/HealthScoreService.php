<?php

namespace App\Services;

use App\Models\ChecklistItem;
use App\Models\MealPlan;
use App\Models\ProgramGoal;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Services\RuleEngine\RuleEngineService;
use Illuminate\Support\Carbon;

/**
 * Weighted composite 0-100 score per docs/08-Knowledge-Base.md §5. The
 * doc names 8 weighted components and their "basis" (e.g. "distance from
 * 18.5-24.9 range") but not the exact decay curve - those are designed
 * here, each documented inline, since a "basis" description alone isn't
 * computable. Never called by AI - the analyze() capability only
 * explains a score this service already produced (docs/06 §4.3), it
 * never recomputes it.
 */
class HealthScoreService
{
    private const WINDOW_DAYS = 7;

    public function __construct(private RuleEngineService $ruleEngineService) {}

    /**
     * @return array{bmi: float, waist: float, sleep: float, water: float, activity: float, weight_trend: float, checklist: float, disease_management: float}
     */
    public function computeBreakdown(User $user): array
    {
        return [
            'bmi' => $this->bmiScore($user),
            'waist' => $this->waistScore($user),
            'sleep' => $this->sleepScore($user),
            'water' => $this->waterScore($user),
            'activity' => $this->activityScore($user),
            'weight_trend' => $this->weightTrendScore($user),
            'checklist' => $this->checklistScore($user),
            'disease_management' => $this->diseaseManagementScore($user),
        ];
    }

    public function computeScore(User $user): float
    {
        return round(array_sum($this->computeBreakdown($user)), 2);
    }

    /**
     * Weight 20. Full marks inside the healthy 18.5-24.9 range; 2 points
     * lost per BMI unit outside it in either direction (a BMI of 30 -
     * 5.1 over - scores ~9.8).
     */
    private function bmiScore(User $user): float
    {
        $bmi = $user->healthProfile?->bmi;

        if ($bmi === null) {
            return 0.0;
        }

        $bmi = (float) $bmi;
        $distance = max(0, 18.5 - $bmi, $bmi - 24.9);

        return round(max(0, 20 - $distance * 2), 2);
    }

    /**
     * Weight 10. IDF Asian-population healthy-waist cutoffs (male <90cm,
     * female <80cm) - relevant for this app's Indonesian userbase. 2
     * points lost per 4cm over the threshold.
     */
    private function waistScore(User $user): float
    {
        $waist = $user->latestWaistCm();
        $gender = $user->healthProfile?->gender;

        if ($waist === null || $gender === null) {
            return 0.0;
        }

        $threshold = $gender === 'male' ? 90 : 80;
        $over = max(0, $waist - $threshold);

        return round(max(0, 10 - $over * 0.5), 2);
    }

    /**
     * Weight 15. Full marks for a 7-day average within the 7-9h target;
     * 5 points lost per hour outside it.
     */
    private function sleepScore(User $user): float
    {
        $avg = $user->sleepLogs()->where('logged_at', '>=', Carbon::today()->subDays(self::WINDOW_DAYS - 1))->avg('sleep_hours');

        if ($avg === null) {
            return 0.0;
        }

        $avg = (float) $avg;
        $distance = max(0, 7 - $avg, $avg - 9);

        return round(max(0, 15 - $distance * 5), 2);
    }

    /**
     * Weight 10. 7-day average daily intake vs rule_engine_output's
     * water_target_ml, capped at 100% (overdrinking past target doesn't
     * earn bonus points).
     */
    private function waterScore(User $user): float
    {
        $logs = $user->waterIntakeLogs()->where('logged_at', '>=', Carbon::today()->subDays(self::WINDOW_DAYS - 1))->get(['logged_at', 'amount_ml']);

        if ($logs->isEmpty()) {
            return 0.0;
        }

        $target = $this->ruleEngineService->evaluate($user)['water_target_ml'] ?? 2000;
        $dailyTotals = $logs->groupBy(fn ($log) => $log->logged_at->toDateString())->map(fn ($day) => $day->sum('amount_ml'));
        $ratio = min(1, $dailyTotals->avg() / $target);

        return round(10 * $ratio, 2);
    }

    /**
     * Weight 15. workout_plans.is_completed rate across the user's
     * active program(s), last 7 days.
     */
    private function activityScore(User $user): float
    {
        $programIds = $user->activePrograms()->pluck('id');

        if ($programIds->isEmpty()) {
            return 0.0;
        }

        $plans = WorkoutPlan::whereIn('user_program_id', $programIds)
            ->where('plan_date', '>=', Carbon::today()->subDays(self::WINDOW_DAYS - 1))
            ->get();

        if ($plans->isEmpty()) {
            return 0.0;
        }

        return round(15 * ($plans->where('is_completed', true)->count() / $plans->count()), 2);
    }

    /**
     * Weight 15. Direction/rate of the last 7 days' weight change vs the
     * active program's goal_type. Fewer than 2 data points in the window
     * means no trend is measurable yet - that's not evidence of a *bad*
     * trend, so it gets half credit rather than 0 (unlike the other
     * components, where no data legitimately means no evidence of the
     * healthy behavior).
     */
    private function weightTrendScore(User $user): float
    {
        $logs = $user->weightLogs()->where('logged_at', '>=', Carbon::today()->subDays(self::WINDOW_DAYS - 1))->orderBy('logged_at')->get();

        if ($logs->count() < 2) {
            return 7.5;
        }

        $goalType = ProgramGoal::whereIn('user_program_id', $user->activePrograms()->pluck('id'))->latest()->value('goal_type') ?? 'maintenance';
        $delta = (float) $logs->last()->weight_kg - (float) $logs->first()->weight_kg;

        return match ($goalType) {
            'weight_loss' => round(max(0, 15 - max(0, $delta) * 10), 2),
            'weight_gain' => round(max(0, 15 - max(0, -$delta) * 10), 2),
            default => round(max(0, 15 - abs($delta) * 10), 2),
        };
    }

    /**
     * Weight 10. checklist_items.is_checked rate across active
     * program(s), last 7 days.
     */
    private function checklistScore(User $user): float
    {
        $programIds = $user->activePrograms()->pluck('id');

        if ($programIds->isEmpty()) {
            return 0.0;
        }

        $items = ChecklistItem::whereIn('user_program_id', $programIds)
            ->where('item_date', '>=', Carbon::today()->subDays(self::WINDOW_DAYS - 1))
            ->get();

        if ($items->isEmpty()) {
            return 0.0;
        }

        return round(10 * ($items->where('is_checked', true)->count() / $items->count()), 2);
    }

    /**
     * Weight 5. There's no per-meal "what did you actually eat" logging
     * in this app - meal_plans.is_completed (completing the AI/Rule-
     * Engine-generated, restriction-compliant plan) is the closest
     * available proxy for "restriction-compliant meal logging". A user
     * with no active disease restrictions has nothing to manage here, so
     * gets full marks by default rather than being penalized for an
     * inapplicable component.
     */
    private function diseaseManagementScore(User $user): float
    {
        $restrictions = $this->ruleEngineService->evaluate($user)['restrictions'] ?? [];

        if (empty($restrictions)) {
            return 5.0;
        }

        $programIds = $user->activePrograms()->pluck('id');

        if ($programIds->isEmpty()) {
            return 0.0;
        }

        $plans = MealPlan::whereIn('user_program_id', $programIds)
            ->where('plan_date', '>=', Carbon::today()->subDays(self::WINDOW_DAYS - 1))
            ->get();

        if ($plans->isEmpty()) {
            return 0.0;
        }

        return round(5 * ($plans->where('is_completed', true)->count() / $plans->count()), 2);
    }
}
