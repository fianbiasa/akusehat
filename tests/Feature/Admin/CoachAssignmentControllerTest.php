<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachAssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
    }

    private function coach(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'coach')->value('id')]);
    }

    public function test_an_admin_can_assign_a_member_to_a_coach()
    {
        $admin = $this->admin();
        $coach = $this->coach();
        $member = User::factory()->create();
        app(SubscriptionService::class)->subscribe($member, Plan::where('slug', 'premium-bulanan')->firstOrFail());

        $this->actingAs($admin)->post('/admin/coach-members', [
            'coach_id' => $coach->id,
            'member_id' => $member->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coach_members', ['coach_id' => $coach->id, 'member_id' => $member->id, 'status' => 'active']);
    }

    public function test_a_member_on_a_plan_without_coach_access_cannot_be_assigned_a_coach()
    {
        $admin = $this->admin();
        $coach = $this->coach();
        $member = User::factory()->create();

        $this->actingAs($admin)->post('/admin/coach-members', [
            'coach_id' => $coach->id,
            'member_id' => $member->id,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('coach_members', ['coach_id' => $coach->id, 'member_id' => $member->id]);
    }

    public function test_the_coach_id_must_actually_belong_to_a_coach_role_user()
    {
        $admin = $this->admin();
        $notACoach = User::factory()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->post('/admin/coach-members', [
            'coach_id' => $notACoach->id,
            'member_id' => $member->id,
        ])->assertSessionHasErrors('coach_id');
    }

    public function test_a_non_admin_cannot_assign_coaches()
    {
        $coach = $this->coach();
        $member = User::factory()->create();

        $this->actingAs($coach)->post('/admin/coach-members', [
            'coach_id' => $coach->id,
            'member_id' => $member->id,
        ])->assertForbidden();
    }

    public function test_an_admin_can_unassign_a_member()
    {
        $admin = $this->admin();
        $coach = $this->coach();
        $member = User::factory()->create();
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $member->programs()->create([
            'program_id' => $program->id, 'coach_id' => $coach->id, 'status' => 'active',
            'start_date' => today(), 'end_date' => today()->addDays(89), 'created_by' => 'ai',
        ]);
        $member->coachAssignments()->create(['coach_id' => $coach->id, 'status' => 'active', 'assigned_at' => now()]);

        $this->actingAs($admin)->delete("/admin/coach-members/{$member->id}")->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coach_members', ['coach_id' => $coach->id, 'member_id' => $member->id, 'status' => 'ended']);
    }
}
