<?php

namespace Tests\Feature\Program;

use App\Events\CheckInSubmitted;
use App\Models\Program;
use App\Models\User;
use App\Models\UserProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChecklistAndDailyTaskTest extends TestCase
{
    use RefreshDatabase;

    private function userProgramFor(User $user): UserProgram
    {
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();

        return $user->programs()->create([
            'program_id' => $program->id, 'status' => 'active',
            'start_date' => today(), 'end_date' => today()->addDays(89), 'created_by' => 'ai',
        ]);
    }

    public function test_a_member_can_toggle_their_own_checklist_item_and_it_fires_check_in_submitted()
    {
        Event::fake([CheckInSubmitted::class]);
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $userProgram = $this->userProgramFor($user);
        $item = $userProgram->checklistItems()->create(['item_date' => today(), 'label' => 'Minum air 2000ml']);

        $this->actingAs($user)->patch("/checklist-items/{$item->id}", ['is_checked' => true])->assertSessionHasNoErrors();

        $this->assertTrue($item->fresh()->is_checked);
        $this->assertNotNull($item->fresh()->checked_at);
        Event::assertDispatched(CheckInSubmitted::class, fn ($event) => $event->user->is($user));
    }

    public function test_a_member_cannot_toggle_another_members_checklist_item()
    {
        $owner = User::factory()->create(['onboarding_completed_at' => now()]);
        $intruder = User::factory()->create(['onboarding_completed_at' => now()]);
        $userProgram = $this->userProgramFor($owner);
        $item = $userProgram->checklistItems()->create(['item_date' => today(), 'label' => 'Minum air 2000ml']);

        $this->actingAs($intruder)->patch("/checklist-items/{$item->id}", ['is_checked' => true])->assertForbidden();
    }

    public function test_a_member_can_toggle_a_daily_task_complete()
    {
        Event::fake([CheckInSubmitted::class]);
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $userProgram = $this->userProgramFor($user);
        $task = $userProgram->dailyTasks()->create([
            'task_date' => today(), 'task_type' => 'workout', 'title' => 'Jalan Kaki 30 menit', 'source' => 'rule_engine',
        ]);

        $this->actingAs($user)->patch("/daily-tasks/{$task->id}", ['is_completed' => true])->assertSessionHasNoErrors();

        $this->assertTrue($task->fresh()->is_completed);
    }

    public function test_a_member_cannot_toggle_another_members_daily_task()
    {
        $owner = User::factory()->create(['onboarding_completed_at' => now()]);
        $intruder = User::factory()->create(['onboarding_completed_at' => now()]);
        $userProgram = $this->userProgramFor($owner);
        $task = $userProgram->dailyTasks()->create([
            'task_date' => today(), 'task_type' => 'workout', 'title' => 'Jalan Kaki 30 menit', 'source' => 'rule_engine',
        ]);

        $this->actingAs($intruder)->patch("/daily-tasks/{$task->id}", ['is_completed' => true])->assertForbidden();
    }
}
