<?php

namespace Tests\Feature\Program;

use App\Jobs\GenerateProgramJob;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class UserProgramControllerTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedMember(): User
    {
        return User::factory()->create(['onboarding_completed_at' => now()]);
    }

    public function test_a_member_can_start_a_new_program_which_queues_generation()
    {
        Bus::fake();
        $user = $this->onboardedMember();
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();

        $this->actingAs($user)->post('/user-programs', ['program_id' => $program->id])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('user_programs', ['user_id' => $user->id, 'program_id' => $program->id]);
        Bus::assertDispatched(GenerateProgramJob::class);
    }

    public function test_the_program_detail_page_renders_for_the_owner()
    {
        Bus::fake();
        $user = $this->onboardedMember();
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $userProgram = $user->programs()->create([
            'program_id' => $program->id, 'status' => 'active',
            'start_date' => today(), 'end_date' => today()->addDays(89), 'created_by' => 'ai',
        ]);

        $this->actingAs($user)->get("/user-programs/{$userProgram->id}")->assertOk();
    }

    public function test_a_non_owner_non_coach_member_cannot_view_another_users_program()
    {
        Bus::fake();
        $owner = $this->onboardedMember();
        $intruder = $this->onboardedMember();
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $userProgram = $owner->programs()->create([
            'program_id' => $program->id, 'status' => 'active',
            'start_date' => today(), 'end_date' => today()->addDays(89), 'created_by' => 'ai',
        ]);

        $this->actingAs($intruder)->get("/user-programs/{$userProgram->id}")->assertForbidden();
    }

    public function test_a_coach_assigned_via_coach_id_can_view_the_program()
    {
        Bus::fake();
        $owner = $this->onboardedMember();
        $coach = User::factory()->create(['role_id' => Role::where('name', 'coach')->value('id'), 'onboarding_completed_at' => now()]);
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $userProgram = $owner->programs()->create([
            'program_id' => $program->id, 'coach_id' => $coach->id, 'status' => 'active',
            'start_date' => today(), 'end_date' => today()->addDays(89), 'created_by' => 'ai',
        ]);

        $this->actingAs($coach)->get("/user-programs/{$userProgram->id}")->assertOk();
    }

    public function test_a_member_can_pause_their_own_program()
    {
        Bus::fake();
        $user = $this->onboardedMember();
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $userProgram = $user->programs()->create([
            'program_id' => $program->id, 'status' => 'active',
            'start_date' => today(), 'end_date' => today()->addDays(89), 'created_by' => 'ai',
        ]);

        $this->actingAs($user)->patch("/user-programs/{$userProgram->id}", ['status' => 'paused'])->assertSessionHasNoErrors();

        $this->assertSame('paused', $userProgram->fresh()->status);
    }

    public function test_regenerate_dispatches_a_generation_job()
    {
        Bus::fake();
        $user = $this->onboardedMember();
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $userProgram = $user->programs()->create([
            'program_id' => $program->id, 'status' => 'active',
            'start_date' => today(), 'end_date' => today()->addDays(89), 'created_by' => 'ai',
        ]);

        $this->actingAs($user)->post("/user-programs/{$userProgram->id}/regenerate")->assertSessionHasNoErrors();

        Bus::assertDispatched(GenerateProgramJob::class, fn ($job) => $job->userProgram->is($userProgram));
    }
}
