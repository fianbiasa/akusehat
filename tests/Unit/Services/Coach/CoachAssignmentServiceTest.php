<?php

namespace Tests\Unit\Services\Coach;

use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use App\Services\Coach\CoachAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function coach(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'coach')->value('id')]);
    }

    private function memberWithActiveProgram(): User
    {
        $member = User::factory()->create();
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $member->programs()->create([
            'program_id' => $program->id, 'status' => 'active',
            'start_date' => today(), 'end_date' => today()->addDays(89), 'created_by' => 'ai',
        ]);

        return $member;
    }

    public function test_assign_creates_an_active_coach_member_row_and_syncs_program_coach_id()
    {
        $coach = $this->coach();
        $member = $this->memberWithActiveProgram();

        $assignment = app(CoachAssignmentService::class)->assign($coach, $member);

        $this->assertSame('active', $assignment->status);
        $this->assertDatabaseHas('coach_members', ['coach_id' => $coach->id, 'member_id' => $member->id, 'status' => 'active']);
        $this->assertSame($coach->id, $member->activePrograms()->first()->coach_id);
    }

    public function test_reassigning_ends_the_previous_active_assignment()
    {
        $coachA = $this->coach();
        $coachB = $this->coach();
        $member = $this->memberWithActiveProgram();

        $service = app(CoachAssignmentService::class);
        $first = $service->assign($coachA, $member);
        $service->assign($coachB, $member);

        $this->assertSame('ended', $first->fresh()->status);
        $this->assertNotNull($first->fresh()->ended_at);
        $this->assertSame($coachB->id, $member->activePrograms()->first()->fresh()->coach_id);
        $this->assertSame(1, $member->coachAssignments()->where('status', 'active')->count());
    }

    /**
     * Regression guard for the schema's "Unique-ish" (coach_id, member_id,
     * status) constraint problem: reassigning a member back to a coach
     * they'd previously left must not violate any unique index, since
     * that would create a second (coach_id, member_id, 'ended') row.
     */
    public function test_a_member_can_be_reassigned_back_to_a_previously_ended_coach()
    {
        $coachA = $this->coach();
        $coachB = $this->coach();
        $member = $this->memberWithActiveProgram();

        $service = app(CoachAssignmentService::class);
        $service->assign($coachA, $member);
        $service->assign($coachB, $member);
        $service->assign($coachA, $member);

        $this->assertSame(2, $member->coachAssignments()->where('coach_id', $coachA->id)->count());
        $this->assertSame(1, $member->coachAssignments()->where('coach_id', $coachA->id)->where('status', 'active')->count());
    }

    public function test_unassign_ends_the_active_assignment_and_clears_program_coach_id()
    {
        $coach = $this->coach();
        $member = $this->memberWithActiveProgram();
        $service = app(CoachAssignmentService::class);
        $service->assign($coach, $member);

        $service->unassign($member);

        $this->assertNull($member->activeCoachAssignment()->first());
        $this->assertNull($member->activePrograms()->first()->fresh()->coach_id);
    }
}
