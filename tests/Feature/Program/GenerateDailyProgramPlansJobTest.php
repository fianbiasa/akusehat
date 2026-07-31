<?php

namespace Tests\Feature\Program;

use App\Jobs\GenerateDailyProgramPlansJob;
use App\Models\Program;
use App\Models\User;
use App\Models\UserProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateDailyProgramPlansJobTest extends TestCase
{
    use RefreshDatabase;

    private function profiledUser(): User
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $user->healthProfile()->create([
            'gender' => 'male',
            'date_of_birth' => now()->subYears(30),
            'height_cm' => 170,
            'initial_weight_kg' => 80,
            'bmi' => 27.7,
            'bmr' => 1700,
            'tdee' => 2000,
        ]);
        $user->lifestyleProfile()->create(['activity_level' => 'light']);

        return $user->fresh();
    }

    private function activeUserProgram(User $user, array $overrides = []): UserProgram
    {
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();

        return $user->programs()->create(array_merge([
            'program_id' => $program->id,
            'status' => 'active',
            'start_date' => today()->subDays(5),
            'end_date' => today()->addDays(84),
            'created_by' => 'ai',
        ], $overrides));
    }

    public function test_it_generates_todays_plan_for_an_active_program_missing_one()
    {
        $userProgram = $this->activeUserProgram($this->profiledUser());

        (new GenerateDailyProgramPlansJob)->handle();

        $this->assertSame(4, $userProgram->mealPlans()->whereDate('plan_date', today())->count());
        $this->assertGreaterThan(0, $userProgram->checklistItems()->whereDate('item_date', today())->count());
    }

    public function test_it_skips_a_program_that_already_has_todays_plan()
    {
        $userProgram = $this->activeUserProgram($this->profiledUser());
        $userProgram->mealPlans()->create([
            'plan_date' => today(), 'meal_type' => 'breakfast', 'source' => 'rule_engine',
        ]);

        (new GenerateDailyProgramPlansJob)->handle();

        $this->assertSame(1, $userProgram->mealPlans()->whereDate('plan_date', today())->count());
        $this->assertSame(0, $userProgram->checklistItems()->whereDate('item_date', today())->count());
    }

    public function test_it_skips_an_inactive_program()
    {
        $userProgram = $this->activeUserProgram($this->profiledUser(), ['status' => 'paused']);

        (new GenerateDailyProgramPlansJob)->handle();

        $this->assertSame(0, $userProgram->mealPlans()->whereDate('plan_date', today())->count());
    }

    public function test_it_skips_a_program_past_its_end_date()
    {
        $userProgram = $this->activeUserProgram($this->profiledUser(), [
            'start_date' => today()->subDays(100),
            'end_date' => today()->subDay(),
        ]);

        (new GenerateDailyProgramPlansJob)->handle();

        $this->assertSame(0, $userProgram->mealPlans()->whereDate('plan_date', today())->count());
    }
}
