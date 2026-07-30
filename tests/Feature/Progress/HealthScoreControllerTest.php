<?php

namespace Tests\Feature\Progress;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthScoreControllerTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedMember(): User
    {
        return User::factory()->create(['onboarding_completed_at' => now()]);
    }

    public function test_a_member_can_see_their_own_health_score_history()
    {
        $user = $this->onboardedMember();
        $user->healthScores()->create(['scored_at' => today(), 'score' => 83, 'breakdown' => ['bmi' => 18], 'created_at' => now()]);

        $response = $this->actingAs($user)->getJson('/progress/health-score');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_today_endpoint_returns_null_when_not_yet_computed()
    {
        $user = $this->onboardedMember();

        $response = $this->actingAs($user)->getJson('/progress/health-score/today');

        $response->assertOk();
        $this->assertArrayNotHasKey('score', $response->json());
    }

    public function test_a_member_cannot_view_another_members_health_score()
    {
        $owner = $this->onboardedMember();
        $intruder = $this->onboardedMember();

        $this->actingAs($intruder)->getJson("/progress/health-score?user_id={$owner->id}")->assertForbidden();
    }

    public function test_an_admin_can_view_any_members_health_score()
    {
        $owner = $this->onboardedMember();
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id'), 'onboarding_completed_at' => now()]);
        $owner->healthScores()->create(['scored_at' => today(), 'score' => 70, 'breakdown' => [], 'created_at' => now()]);

        $this->actingAs($admin)->getJson("/progress/health-score?user_id={$owner->id}")->assertOk()->assertJsonCount(1);
    }
}
