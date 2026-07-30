<?php

namespace Tests\Feature\Program;

use App\Models\Program;
use App\Models\User;
use App\Models\UserProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealAndWorkoutPlanOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function activeUserProgram(User $user): UserProgram
    {
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();

        return $user->programs()->create([
            'program_id' => $program->id,
            'status' => 'active',
            'start_date' => today(),
            'end_date' => today()->addDays(89),
            'created_by' => 'ai',
        ]);
    }

    public function test_toggling_is_completed_does_not_record_an_activity_log_entry()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $userProgram = $this->activeUserProgram($user);
        $mealPlan = $userProgram->mealPlans()->create(['plan_date' => today(), 'meal_type' => 'breakfast', 'source' => 'rule_engine']);

        $this->actingAs($user)->patch("/meal-plans/{$mealPlan->id}", ['is_completed' => true])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('activity_logs', ['action' => 'meal_plan.overridden']);
    }

    public function test_manually_overriding_a_meal_plans_calories_records_an_activity_log_entry()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $userProgram = $this->activeUserProgram($user);
        $mealPlan = $userProgram->mealPlans()->create(['plan_date' => today(), 'meal_type' => 'breakfast', 'source' => 'ai']);

        $this->actingAs($user)->patch("/meal-plans/{$mealPlan->id}", ['total_calories' => 350])->assertSessionHasNoErrors();

        $this->assertSame('manual', $mealPlan->fresh()->source);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'meal_plan.overridden',
            'subject_id' => $mealPlan->id,
        ]);
    }

    public function test_manually_overriding_a_workout_plans_duration_records_an_activity_log_entry()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $userProgram = $this->activeUserProgram($user);
        $workoutPlan = $userProgram->workoutPlans()->create(['plan_date' => today(), 'workout_type' => 'cardio', 'intensity' => 'low', 'source' => 'ai']);

        $this->actingAs($user)->patch("/workout-plans/{$workoutPlan->id}", ['duration_minutes' => 45])->assertSessionHasNoErrors();

        $this->assertSame('manual', $workoutPlan->fresh()->source);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'workout_plan.overridden',
            'subject_id' => $workoutPlan->id,
        ]);
    }

    public function test_a_non_owner_non_coach_cannot_override_a_meal_plan()
    {
        $owner = User::factory()->create(['onboarding_completed_at' => now()]);
        $intruder = User::factory()->create(['onboarding_completed_at' => now()]);
        $userProgram = $this->activeUserProgram($owner);
        $mealPlan = $userProgram->mealPlans()->create(['plan_date' => today(), 'meal_type' => 'breakfast', 'source' => 'ai']);

        $this->actingAs($intruder)->patch("/meal-plans/{$mealPlan->id}", ['total_calories' => 999])->assertForbidden();
    }
}
