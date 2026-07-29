<?php

namespace Database\Seeders;

use App\Models\RuleEngineRule;
use Illuminate\Database\Seeder;

class RuleEngineRuleSeeder extends Seeder
{
    /**
     * Baseline rules per docs/08-Knowledge-Base.md §3.1 (the three worked
     * examples) plus enough coverage across every category for
     * RuleEngineService to produce a sensible plan without an AI call.
     * disease_restriction rules sit in the 200+ priority band so they
     * always apply on top of - never get averaged with - the generic
     * demographic rules at 100.
     */
    public function run(): void
    {
        $rules = [
            [
                'category' => 'calorie_target',
                'name' => 'Overweight deficit',
                'condition' => ['bmi' => ['>=' => 25]],
                'action' => ['calorie_deficit_pct' => 20, 'min_calorie_floor' => 1200],
                'priority' => 100,
            ],
            [
                'category' => 'calorie_target',
                'name' => 'Obese deficit',
                'condition' => ['bmi' => ['>=' => 30]],
                'action' => ['calorie_deficit_pct' => 25, 'min_calorie_floor' => 1200],
                'priority' => 110,
            ],
            [
                'category' => 'workout_level',
                'name' => 'High BMI beginner cap',
                'condition' => ['and' => [['bmi' => ['>=' => 27]], ['activity_level' => ['in' => ['sedentary', 'light']]]]],
                'action' => ['workout_level' => 'beginner'],
                'priority' => 100,
            ],
            [
                'category' => 'workout_level',
                'name' => 'Active and healthy BMI',
                'condition' => ['and' => [['bmi' => ['<' => 27]], ['activity_level' => ['in' => ['moderate', 'heavy']]]]],
                'action' => ['workout_level' => 'intermediate'],
                'priority' => 100,
            ],
            [
                'category' => 'water_target',
                'name' => 'Baseline hydration',
                'condition' => [],
                'action' => ['ml_per_kg' => 33],
                'priority' => 100,
            ],
            [
                'category' => 'macro_split',
                'name' => 'Diabetes lower-carb macro split',
                'condition' => ['diseases' => ['in' => ['diabetes-tipe-2']]],
                'action' => ['protein_pct' => 30, 'carbs_pct' => 30, 'fat_pct' => 40],
                'priority' => 150,
            ],
            [
                'category' => 'disease_restriction',
                'name' => 'Gout purine restriction',
                'condition' => ['diseases' => ['in' => ['asam-urat']]],
                'action' => ['add_restriction' => 'low_purine', 'exclude_exercise_tags' => ['high_impact_joint_stress']],
                'priority' => 200,
            ],
            [
                'category' => 'disease_restriction',
                'name' => 'Hypertension sodium restriction',
                'condition' => ['diseases' => ['in' => ['hipertensi']]],
                'action' => ['add_restriction' => 'low_sodium', 'exclude_exercise_tags' => ['heavy_lifting_valsalva']],
                'priority' => 200,
            ],
            [
                'category' => 'disease_restriction',
                'name' => 'Diabetes glycemic restriction',
                'condition' => ['diseases' => ['in' => ['diabetes-tipe-2']]],
                'action' => ['add_restriction' => 'low_glycemic_index'],
                'priority' => 200,
            ],
            [
                'category' => 'disease_restriction',
                'name' => 'Cholesterol saturated fat restriction',
                'condition' => ['diseases' => ['in' => ['kolesterol-tinggi']]],
                'action' => ['add_restriction' => 'low_saturated_fat'],
                'priority' => 200,
            ],
            [
                'category' => 'disease_restriction',
                'name' => 'GERD restriction',
                'condition' => ['diseases' => ['in' => ['tukak-lambung-gerd']]],
                'action' => ['add_restriction' => 'small_frequent_meals', 'exclude_exercise_tags' => ['inversion_exercises']],
                'priority' => 200,
            ],
        ];

        foreach ($rules as $rule) {
            RuleEngineRule::updateOrCreate(
                ['category' => $rule['category'], 'name' => $rule['name']],
                [...$rule, 'is_active' => true],
            );
        }
    }
}
