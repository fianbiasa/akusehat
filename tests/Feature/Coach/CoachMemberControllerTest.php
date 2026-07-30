<?php

namespace Tests\Feature\Coach;

use App\Models\Role;
use App\Models\User;
use App\Services\Coach\CoachAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachMemberControllerTest extends TestCase
{
    use RefreshDatabase;

    private function coach(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'coach')->value('id')]);
    }

    public function test_an_assigned_coach_can_view_the_member_detail_page()
    {
        $coach = $this->coach();
        $member = User::factory()->create();
        app(CoachAssignmentService::class)->assign($coach, $member);

        $this->actingAs($coach)->get("/coach/members/{$member->id}")->assertOk();
    }

    public function test_a_coach_cannot_view_a_member_not_assigned_to_them()
    {
        $coach = $this->coach();
        $otherCoach = $this->coach();
        $member = User::factory()->create();
        app(CoachAssignmentService::class)->assign($otherCoach, $member);

        $this->actingAs($coach)->get("/coach/members/{$member->id}")->assertForbidden();
    }

    public function test_the_advisory_panel_is_omitted_when_the_member_has_no_concerns_or_pending_recommendations()
    {
        $coach = $this->coach();
        $member = User::factory()->create();
        app(CoachAssignmentService::class)->assign($coach, $member);

        $response = $this->actingAs($coach)->get("/coach/members/{$member->id}");

        $response->assertInertia(fn ($page) => $page->where('advisory', null));
    }

    public function test_pending_recommendations_are_listed_for_the_assigned_coach()
    {
        $coach = $this->coach();
        $member = User::factory()->create();
        app(CoachAssignmentService::class)->assign($coach, $member);
        $member->aiRecommendations()->create([
            'type' => 'meal_adjustment', 'content' => ['detail' => 'Kurangi porsi nasi malam'], 'status' => 'pending',
        ]);

        $response = $this->actingAs($coach)->get("/coach/members/{$member->id}");

        $response->assertInertia(fn ($page) => $page->has('pendingRecommendations', 1));
    }
}
