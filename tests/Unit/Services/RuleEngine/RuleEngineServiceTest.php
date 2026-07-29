<?php

namespace Tests\Unit\Services\RuleEngine;

use App\Models\KbDisease;
use App\Models\RuleEngineRule;
use App\Models\User;
use App\Services\RuleEngine\RuleEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuleEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // BaselineSeeder (auto-run by Tests\TestCase) already seeds 11
        // production rules; these tests need full control over which
        // rules exist to assert conflict resolution deterministically.
        RuleEngineRule::query()->delete();
    }

    private function profiledUser(array $health = [], ?string $activityLevel = 'sedentary'): User
    {
        $user = User::factory()->create();
        $user->healthProfile()->create([
            'gender' => 'male',
            'date_of_birth' => now()->subYears(30),
            'height_cm' => 170,
            'initial_weight_kg' => 80,
            'bmi' => 27.7,
            'bmr' => 1700,
            'tdee' => 2000,
            ...$health,
        ]);
        $user->lifestyleProfile()->create(['activity_level' => $activityLevel]);

        return $user->fresh();
    }

    /** kb_diseases is already seeded by BaselineSeeder (KbDiseaseSeeder) - reuse it, don't duplicate slugs. */
    private function disease(string $slug): KbDisease
    {
        return KbDisease::where('slug', $slug)->firstOrFail();
    }

    public function test_it_returns_the_documented_defaults_when_no_rules_match()
    {
        $result = app(RuleEngineService::class)->evaluate($this->profiledUser(['bmi' => 20]));

        $this->assertSame(2000, $result['calorie_target']); // falls back to tdee
        $this->assertSame(['protein_pct' => 30, 'carbs_pct' => 40, 'fat_pct' => 30], $result['macro_split']);
        $this->assertSame('beginner', $result['workout_level']);
        $this->assertSame([], $result['restrictions']);
    }

    public function test_higher_priority_rule_wins_on_a_key_conflict_within_a_category()
    {
        RuleEngineRule::create([
            'category' => 'workout_level', 'name' => 'low priority', 'priority' => 100,
            'condition' => ['bmi' => ['>=' => 20]], 'action' => ['workout_level' => 'beginner'],
        ]);
        RuleEngineRule::create([
            'category' => 'workout_level', 'name' => 'high priority', 'priority' => 200,
            'condition' => ['bmi' => ['>=' => 20]], 'action' => ['workout_level' => 'advanced'],
        ]);

        $result = app(RuleEngineService::class)->evaluate($this->profiledUser(['bmi' => 25]));

        $this->assertSame('advanced', $result['workout_level']);
    }

    public function test_priority_order_is_independent_of_insertion_order()
    {
        RuleEngineRule::create([
            'category' => 'workout_level', 'name' => 'high priority', 'priority' => 200,
            'condition' => ['bmi' => ['>=' => 20]], 'action' => ['workout_level' => 'advanced'],
        ]);
        RuleEngineRule::create([
            'category' => 'workout_level', 'name' => 'low priority', 'priority' => 100,
            'condition' => ['bmi' => ['>=' => 20]], 'action' => ['workout_level' => 'beginner'],
        ]);

        $result = app(RuleEngineService::class)->evaluate($this->profiledUser(['bmi' => 25]));

        $this->assertSame('advanced', $result['workout_level']);
    }

    public function test_calorie_target_applies_deficit_percentage_and_floor()
    {
        RuleEngineRule::create([
            'category' => 'calorie_target', 'name' => 'deficit', 'priority' => 100,
            'condition' => ['bmi' => ['>=' => 25]], 'action' => ['calorie_deficit_pct' => 20, 'min_calorie_floor' => 1200],
        ]);

        $result = app(RuleEngineService::class)->evaluate($this->profiledUser(['bmi' => 27, 'tdee' => 2000]));

        $this->assertSame(1600, $result['calorie_target']); // 2000 * 0.8
    }

    public function test_calorie_floor_is_respected_even_with_a_large_deficit()
    {
        RuleEngineRule::create([
            'category' => 'calorie_target', 'name' => 'aggressive deficit', 'priority' => 100,
            'condition' => ['bmi' => ['>=' => 25]], 'action' => ['calorie_deficit_pct' => 80, 'min_calorie_floor' => 1200],
        ]);

        $result = app(RuleEngineService::class)->evaluate($this->profiledUser(['bmi' => 27, 'tdee' => 1300]));

        // 1300 * 0.2 = 260, which is below the 1200 floor.
        $this->assertSame(1200, $result['calorie_target']);
    }

    public function test_disease_restriction_rules_accumulate_across_multiple_matching_diseases_instead_of_overwriting()
    {
        $gout = $this->disease('asam-urat');
        $hypertension = $this->disease('hipertensi');

        RuleEngineRule::create([
            'category' => 'disease_restriction', 'name' => 'gout', 'priority' => 200,
            'condition' => ['diseases' => ['in' => ['asam-urat']]],
            'action' => ['add_restriction' => 'low_purine', 'exclude_exercise_tags' => ['high_impact_joint_stress']],
        ]);
        RuleEngineRule::create([
            'category' => 'disease_restriction', 'name' => 'hypertension', 'priority' => 200,
            'condition' => ['diseases' => ['in' => ['hipertensi']]],
            'action' => ['add_restriction' => 'low_sodium'],
        ]);

        $user = $this->profiledUser();
        $user->diseases()->create(['kb_disease_id' => $gout->id]);
        $user->diseases()->create(['kb_disease_id' => $hypertension->id]);

        $result = app(RuleEngineService::class)->evaluate($user->fresh());

        $this->assertEqualsCanonicalizing(['low_purine', 'high_impact_joint_stress', 'low_sodium'], $result['restrictions']);
    }

    public function test_an_inactive_rule_never_matches()
    {
        RuleEngineRule::create([
            'category' => 'workout_level', 'name' => 'inactive rule', 'priority' => 999, 'is_active' => false,
            'condition' => ['bmi' => ['>=' => 20]], 'action' => ['workout_level' => 'advanced'],
        ]);

        $result = app(RuleEngineService::class)->evaluate($this->profiledUser(['bmi' => 25]));

        $this->assertSame('beginner', $result['workout_level']);
    }

    public function test_water_target_scales_with_body_weight()
    {
        RuleEngineRule::create([
            'category' => 'water_target', 'name' => 'baseline', 'priority' => 100,
            'condition' => [], 'action' => ['ml_per_kg' => 30],
        ]);

        $result = app(RuleEngineService::class)->evaluate($this->profiledUser(['initial_weight_kg' => 80]));

        $this->assertSame(2400, $result['water_target_ml']);
    }

    public function test_the_worked_example_from_the_knowledge_base_doc_produces_the_documented_baseline_shape()
    {
        RuleEngineRule::create([
            'category' => 'calorie_target', 'name' => 'Overweight deficit', 'priority' => 100,
            'condition' => ['bmi' => ['>=' => 25]], 'action' => ['calorie_deficit_pct' => 20, 'min_calorie_floor' => 1200],
        ]);
        RuleEngineRule::create([
            'category' => 'workout_level', 'name' => 'High BMI beginner cap', 'priority' => 100,
            'condition' => ['and' => [['bmi' => ['>=' => 27]], ['activity_level' => ['in' => ['sedentary', 'light']]]]],
            'action' => ['workout_level' => 'beginner'],
        ]);
        RuleEngineRule::create([
            'category' => 'water_target', 'name' => 'baseline', 'priority' => 100,
            'condition' => [], 'action' => ['ml_per_kg' => 33],
        ]);
        $goutDisease = $this->disease('asam-urat');
        RuleEngineRule::create([
            'category' => 'disease_restriction', 'name' => 'Gout purine restriction', 'priority' => 200,
            'condition' => ['diseases' => ['in' => ['asam-urat']]],
            'action' => ['add_restriction' => 'low_purine', 'exclude_exercise_tags' => ['high_impact_joint_stress']],
        ]);

        $user = $this->profiledUser(['bmi' => 28, 'tdee' => 2200, 'initial_weight_kg' => 80], 'light');
        $user->diseases()->create(['kb_disease_id' => $goutDisease->id]);

        $result = app(RuleEngineService::class)->evaluate($user->fresh());

        $this->assertSame([
            'calorie_target' => 1760, // 2200 * 0.8
            'macro_split' => ['protein_pct' => 30, 'carbs_pct' => 40, 'fat_pct' => 30],
            'workout_level' => 'beginner',
            'water_target_ml' => 2640, // 80 * 33
            'restrictions' => ['low_purine', 'high_impact_joint_stress'],
        ], $result);
    }
}
