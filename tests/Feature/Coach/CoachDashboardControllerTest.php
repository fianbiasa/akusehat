<?php

namespace Tests\Feature\Coach;

use App\Models\Role;
use App\Models\User;
use App\Services\Coach\CoachAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function coach(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'coach')->value('id')]);
    }

    public function test_a_coach_can_view_their_dashboard_with_no_members()
    {
        $this->actingAs($this->coach())->get('/coach/dashboard')->assertOk();
    }

    public function test_a_non_coach_cannot_view_the_coach_dashboard()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/coach/dashboard')->assertForbidden();
    }

    public function test_a_member_with_a_concern_memory_is_flagged_needing_attention()
    {
        $coach = $this->coach();
        $member = User::factory()->create();
        app(CoachAssignmentService::class)->assign($coach, $member);
        $member->aiMemories()->create(['memory_type' => 'concern', 'summary' => 'Berat stagnan 20 hari', 'data' => []]);

        $response = $this->actingAs($coach)->get('/coach/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->where('members.0.needs_attention', true)
            ->where('concerns.0.reason', 'Berat stagnan 20 hari')
        );
    }

    public function test_a_member_with_no_concerns_is_not_flagged()
    {
        $coach = $this->coach();
        $member = User::factory()->create();
        app(CoachAssignmentService::class)->assign($coach, $member);

        $response = $this->actingAs($coach)->get('/coach/dashboard');

        $response->assertInertia(fn ($page) => $page->where('members.0.needs_attention', false)->has('concerns', 0));
    }

    public function test_an_ended_assignment_does_not_appear_on_the_dashboard()
    {
        $coach = $this->coach();
        $member = User::factory()->create();
        $service = app(CoachAssignmentService::class);
        $service->assign($coach, $member);
        $service->unassign($member);

        $response = $this->actingAs($coach)->get('/coach/dashboard');

        $response->assertInertia(fn ($page) => $page->where('memberCount', 0));
    }
}
