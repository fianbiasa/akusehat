<?php

namespace App\Services\RuleEngine;

use App\Models\RuleEngineRule;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Deterministic condition -> action evaluator (docs/08-Knowledge-Base.md §3).
 * Never calls an AI provider - this always works even if every AI provider
 * is down (04-Architecture.md, "Rule Engine + last-known-good plan always
 * available offline of AI").
 */
class RuleEngineService
{
    public function __construct(private RuleEngineConditionEvaluator $evaluator) {}

    /**
     * Always returns the fixed shape documented in
     * docs/08-Knowledge-Base.md §3.3 - this is what gets injected into
     * every AI prompt as rule_engine_output, and is also usable standalone
     * (no AI call) as the "AI down" degraded-mode plan baseline.
     *
     * @return array{calorie_target: int, macro_split: array{protein_pct: int, carbs_pct: int, fat_pct: int}, workout_level: string, water_target_ml: int, restrictions: array<int, string>}
     */
    public function evaluate(User $user): array
    {
        $context = $this->buildContext($user);
        $rulesByCategory = RuleEngineRule::where('is_active', true)->get()->groupBy('category');

        $calorieAction = $this->mergeCategory($rulesByCategory->get('calorie_target', collect()), $context);
        $macroAction = $this->mergeCategory($rulesByCategory->get('macro_split', collect()), $context);
        $workoutAction = $this->mergeCategory($rulesByCategory->get('workout_level', collect()), $context);
        $waterAction = $this->mergeCategory($rulesByCategory->get('water_target', collect()), $context);
        $restrictions = $this->accumulateRestrictions($rulesByCategory->get('disease_restriction', collect()), $context);

        return [
            'calorie_target' => $this->resolveCalorieTarget($calorieAction, $context),
            'macro_split' => [
                'protein_pct' => $macroAction['protein_pct'] ?? 30,
                'carbs_pct' => $macroAction['carbs_pct'] ?? 40,
                'fat_pct' => $macroAction['fat_pct'] ?? 30,
            ],
            'workout_level' => $workoutAction['workout_level'] ?? 'beginner',
            'water_target_ml' => $this->resolveWaterTarget($waterAction, $context),
            'restrictions' => $restrictions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(User $user): array
    {
        $health = $user->healthProfile;
        $lifestyle = $user->lifestyleProfile;

        return [
            'bmi' => $health?->bmi !== null ? (float) $health->bmi : null,
            'age' => $health?->date_of_birth?->age,
            'gender' => $health?->gender,
            'activity_level' => $lifestyle?->activity_level,
            'diseases' => $user->diseases()->with('disease:id,slug')->get()->pluck('disease.slug')->filter()->values()->all(),
            'weight_kg' => $user->latestWeightKg(),
            'tdee' => $health?->tdee !== null ? (float) $health->tdee : null,
        ];
    }

    /**
     * Rules matching within a category have their actions merged; on a key
     * conflict, the higher-priority rule wins (applied last in ascending
     * order, so array_merge lets it overwrite).
     *
     * @return array<string, mixed>
     */
    private function mergeCategory(Collection $rules, array $context): array
    {
        return $rules
            ->filter(fn (RuleEngineRule $rule) => $this->evaluator->evaluate($rule->condition, $context))
            ->sortBy('priority')
            ->reduce(fn (array $carry, RuleEngineRule $rule) => [...$carry, ...$rule->action], []);
    }

    /**
     * Unlike other categories, disease_restriction rules union rather than
     * overwrite: a user with two conditions needs both sets of
     * restrictions, not just the higher-priority one.
     *
     * @return array<int, string>
     */
    private function accumulateRestrictions(Collection $rules, array $context): array
    {
        $restrictions = collect();

        foreach ($rules->filter(fn (RuleEngineRule $rule) => $this->evaluator->evaluate($rule->condition, $context)) as $rule) {
            if (isset($rule->action['add_restriction'])) {
                $restrictions->push($rule->action['add_restriction']);
            }

            foreach ($rule->action['exclude_exercise_tags'] ?? [] as $tag) {
                $restrictions->push($tag);
            }
        }

        return $restrictions->unique()->values()->all();
    }

    private function resolveCalorieTarget(array $action, array $context): int
    {
        $tdee = $context['tdee'] ?? 2000;

        if (! isset($action['calorie_deficit_pct']) && ! isset($action['calorie_surplus_pct']) && ! isset($action['calorie_target'])) {
            return (int) round($tdee);
        }

        if (isset($action['calorie_target'])) {
            return (int) round($action['calorie_target']);
        }

        $adjustmentPct = ($action['calorie_surplus_pct'] ?? 0) - ($action['calorie_deficit_pct'] ?? 0);
        $target = $tdee * (1 + $adjustmentPct / 100);

        return (int) round(max($target, $action['min_calorie_floor'] ?? 0));
    }

    private function resolveWaterTarget(array $action, array $context): int
    {
        if (isset($action['water_target_ml'])) {
            return (int) round($action['water_target_ml']);
        }

        $weightKg = $context['weight_kg'] ?? 70;
        $mlPerKg = $action['ml_per_kg'] ?? 33;

        return (int) round($weightKg * $mlPerKg);
    }
}
