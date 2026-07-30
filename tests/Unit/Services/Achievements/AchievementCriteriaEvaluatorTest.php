<?php

namespace Tests\Unit\Services\Achievements;

use App\Models\Program;
use App\Models\User;
use App\Services\Achievements\AchievementCriteriaEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementCriteriaEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private function evaluator(): AchievementCriteriaEvaluator
    {
        return new AchievementCriteriaEvaluator;
    }

    private function activeUserProgram(User $user, string $startDate)
    {
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();

        return $user->programs()->create([
            'program_id' => $program->id,
            'status' => 'active',
            'start_date' => $startDate,
            'created_by' => 'ai',
        ]);
    }

    public function test_weight_loss_kg_matches_when_the_loss_meets_the_target()
    {
        $user = User::factory()->create();
        $user->weightLogs()->create(['logged_at' => now()->subDays(30), 'weight_kg' => 80]);
        $user->weightLogs()->create(['logged_at' => now(), 'weight_kg' => 74]);

        $this->assertTrue($this->evaluator()->matches($user, ['type' => 'weight_loss_kg', 'kg' => 5]));
        $this->assertFalse($this->evaluator()->matches($user, ['type' => 'weight_loss_kg', 'kg' => 10]));
    }

    public function test_weight_loss_kg_is_false_without_at_least_two_distinct_data_points()
    {
        $user = User::factory()->create();
        $user->weightLogs()->create(['logged_at' => now(), 'weight_kg' => 80]);

        $this->assertFalse($this->evaluator()->matches($user, ['type' => 'weight_loss_kg', 'kg' => 1]));
    }

    public function test_checklist_streak_days_matches_only_when_every_day_in_the_window_is_fully_checked()
    {
        $user = User::factory()->create();
        $userProgram = $this->activeUserProgram($user, now()->subDays(10)->toDateString());

        for ($i = 0; $i < 7; $i++) {
            $userProgram->checklistItems()->create(['item_date' => now()->subDays($i), 'label' => 'Minum air', 'is_checked' => true]);
        }

        $this->assertTrue($this->evaluator()->matches($user, ['type' => 'checklist_streak_days', 'days' => 7]));
    }

    public function test_checklist_streak_days_fails_when_one_day_in_the_window_is_incomplete()
    {
        $user = User::factory()->create();
        $userProgram = $this->activeUserProgram($user, now()->subDays(10)->toDateString());

        for ($i = 0; $i < 7; $i++) {
            $userProgram->checklistItems()->create(['item_date' => now()->subDays($i), 'label' => 'Minum air', 'is_checked' => $i !== 3]);
        }

        $this->assertFalse($this->evaluator()->matches($user, ['type' => 'checklist_streak_days', 'days' => 7]));
    }

    public function test_checklist_streak_days_fails_when_a_day_has_no_checklist_at_all()
    {
        $user = User::factory()->create();
        $this->activeUserProgram($user, now()->subDays(10)->toDateString());

        $this->assertFalse($this->evaluator()->matches($user, ['type' => 'checklist_streak_days', 'days' => 7]));
    }

    public function test_program_milestone_days_matches_once_the_program_is_old_enough()
    {
        $user = User::factory()->create();
        $this->activeUserProgram($user, now()->subDays(31)->toDateString());

        $this->assertTrue($this->evaluator()->matches($user, ['type' => 'program_milestone_days', 'days' => 30]));
        $this->assertFalse($this->evaluator()->matches($user, ['type' => 'program_milestone_days', 'days' => 90]));
    }

    public function test_an_unknown_criteria_type_never_matches()
    {
        $user = User::factory()->create();

        $this->assertFalse($this->evaluator()->matches($user, ['type' => 'something_undefined']));
    }
}
