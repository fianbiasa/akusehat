<?php

namespace Tests\Unit\Services;

use App\Models\KbDisease;
use App\Models\Program;
use App\Models\User;
use App\Models\UserProgram;
use App\Services\HealthScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthScoreServiceTest extends TestCase
{
    use RefreshDatabase;

    private function profiledUser(array $health = []): User
    {
        $user = User::factory()->create();
        $user->healthProfile()->create([
            'gender' => 'male',
            'date_of_birth' => now()->subYears(30),
            'height_cm' => 170,
            'initial_weight_kg' => 70,
            'bmr' => 1700,
            'tdee' => 2310,
            ...$health,
        ]);
        $user->lifestyleProfile()->create(['activity_level' => 'sedentary']);

        return $user->fresh();
    }

    private function activeProgram(User $user, string $goalType = 'maintenance'): UserProgram
    {
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $userProgram = $user->programs()->create([
            'program_id' => $program->id, 'status' => 'active',
            'start_date' => today()->subDays(6), 'end_date' => today()->addDays(83), 'created_by' => 'ai',
        ]);
        $userProgram->goals()->create(['goal_type' => $goalType]);

        return $userProgram;
    }

    public function test_bmi_score_is_full_marks_inside_the_healthy_range()
    {
        $user = $this->profiledUser(['bmi' => 22.0]);

        $this->assertSame(20.0, app(HealthScoreService::class)->computeBreakdown($user)['bmi']);
    }

    public function test_bmi_score_decays_outside_the_healthy_range()
    {
        $user = $this->profiledUser(['bmi' => 27.7]);

        // distance = 27.7 - 24.9 = 2.8; score = 20 - 2.8*2 = 14.4
        $this->assertSame(14.4, app(HealthScoreService::class)->computeBreakdown($user)['bmi']);
    }

    public function test_bmi_score_clamps_at_zero_for_a_very_high_bmi()
    {
        $user = $this->profiledUser(['bmi' => 40]);

        $this->assertSame(0.0, app(HealthScoreService::class)->computeBreakdown($user)['bmi']);
    }

    public function test_waist_score_uses_the_male_threshold()
    {
        $user = $this->profiledUser();
        $user->waistLogs()->create(['logged_at' => today(), 'waist_cm' => 100, 'created_at' => now()]);

        // over = 100 - 90 = 10; score = 10 - 10*0.5 = 5
        $this->assertSame(5.0, app(HealthScoreService::class)->computeBreakdown($user)['waist']);
    }

    public function test_waist_score_is_full_marks_at_exactly_the_threshold()
    {
        $user = $this->profiledUser(['gender' => 'female']);
        $user->waistLogs()->create(['logged_at' => today(), 'waist_cm' => 80, 'created_at' => now()]);

        $this->assertSame(10.0, app(HealthScoreService::class)->computeBreakdown($user)['waist']);
    }

    public function test_sleep_score_decays_below_the_target_range()
    {
        $user = $this->profiledUser();
        $user->sleepLogs()->create(['logged_at' => today(), 'sleep_hours' => 6, 'created_at' => now()]);

        // distance = 7 - 6 = 1; score = 15 - 1*5 = 10
        $this->assertSame(10.0, app(HealthScoreService::class)->computeBreakdown($user)['sleep']);
    }

    public function test_sleep_score_is_full_marks_inside_the_target_range()
    {
        $user = $this->profiledUser();
        $user->sleepLogs()->create(['logged_at' => today(), 'sleep_hours' => 8, 'created_at' => now()]);

        $this->assertSame(15.0, app(HealthScoreService::class)->computeBreakdown($user)['sleep']);
    }

    public function test_water_score_is_proportional_to_the_rule_engine_target()
    {
        $user = $this->profiledUser(); // weight 70kg -> target 70*33 = 2310ml
        $user->waterIntakeLogs()->create(['logged_at' => today(), 'amount_ml' => 1155, 'created_at' => now()]);

        // ratio = 1155 / 2310 = 0.5; score = 10 * 0.5 = 5
        $this->assertSame(5.0, app(HealthScoreService::class)->computeBreakdown($user)['water']);
    }

    public function test_water_score_caps_at_full_marks_when_exceeding_target()
    {
        $user = $this->profiledUser();
        $user->waterIntakeLogs()->create(['logged_at' => today(), 'amount_ml' => 5000, 'created_at' => now()]);

        $this->assertSame(10.0, app(HealthScoreService::class)->computeBreakdown($user)['water']);
    }

    public function test_activity_score_matches_workout_completion_rate()
    {
        $user = $this->profiledUser();
        $userProgram = $this->activeProgram($user);

        for ($i = 0; $i < 7; $i++) {
            $userProgram->workoutPlans()->create([
                'plan_date' => today()->subDays($i), 'is_completed' => $i < 4, 'source' => 'ai',
            ]);
        }

        // 4/7 completed; score = 15 * 4/7
        $this->assertEqualsWithDelta(15 * 4 / 7, app(HealthScoreService::class)->computeBreakdown($user)['activity'], 0.01);
    }

    public function test_checklist_score_matches_completion_rate()
    {
        $user = $this->profiledUser();
        $userProgram = $this->activeProgram($user);

        for ($i = 0; $i < 10; $i++) {
            $userProgram->checklistItems()->create([
                'item_date' => today(), 'label' => "Item {$i}", 'is_checked' => $i < 8,
            ]);
        }

        $this->assertSame(8.0, app(HealthScoreService::class)->computeBreakdown($user)['checklist']);
    }

    public function test_weight_trend_score_rewards_loss_when_the_goal_is_weight_loss()
    {
        $user = $this->profiledUser();
        $this->activeProgram($user, 'weight_loss');

        $user->weightLogs()->create(['logged_at' => today()->subDays(6), 'weight_kg' => 80, 'created_at' => now()]);
        $user->weightLogs()->create(['logged_at' => today(), 'weight_kg' => 79, 'created_at' => now()]);

        $this->assertSame(15.0, app(HealthScoreService::class)->computeBreakdown($user)['weight_trend']);
    }

    public function test_weight_trend_score_penalizes_gain_when_the_goal_is_weight_loss()
    {
        $user = $this->profiledUser();
        $this->activeProgram($user, 'weight_loss');

        $user->weightLogs()->create(['logged_at' => today()->subDays(6), 'weight_kg' => 80, 'created_at' => now()]);
        $user->weightLogs()->create(['logged_at' => today(), 'weight_kg' => 81, 'created_at' => now()]);

        // gained 1kg against a weight_loss goal; score = 15 - 1*10 = 5
        $this->assertSame(5.0, app(HealthScoreService::class)->computeBreakdown($user)['weight_trend']);
    }

    public function test_weight_trend_score_defaults_to_half_credit_with_insufficient_data()
    {
        $user = $this->profiledUser();
        $this->activeProgram($user, 'weight_loss');

        $this->assertSame(7.5, app(HealthScoreService::class)->computeBreakdown($user)['weight_trend']);
    }

    public function test_disease_management_score_is_full_marks_with_no_active_restrictions()
    {
        $user = $this->profiledUser();

        $this->assertSame(5.0, app(HealthScoreService::class)->computeBreakdown($user)['disease_management']);
    }

    public function test_disease_management_score_matches_meal_plan_completion_when_restrictions_apply()
    {
        $user = $this->profiledUser();
        $userProgram = $this->activeProgram($user);
        $user->diseases()->create(['kb_disease_id' => KbDisease::where('slug', 'hipertensi')->firstOrFail()->id, 'status' => 'active']);

        for ($i = 0; $i < 4; $i++) {
            $userProgram->mealPlans()->create([
                'plan_date' => today()->subDays($i), 'meal_type' => 'lunch', 'is_completed' => $i < 3, 'source' => 'ai',
            ]);
        }

        $this->assertSame(3.75, app(HealthScoreService::class)->computeBreakdown($user)['disease_management']);
    }

    public function test_the_total_score_is_the_sum_of_all_components()
    {
        $user = $this->profiledUser(['bmi' => 22.0]);

        $service = app(HealthScoreService::class);
        $breakdown = $service->computeBreakdown($user);

        $this->assertSame(round(array_sum($breakdown), 2), $service->computeScore($user));
    }
}
