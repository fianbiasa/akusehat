<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use App\Services\Coach\CoachAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalog_lists_every_seeded_achievement()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $response = $this->actingAs($user)->getJson('/achievements');

        $response->assertOk();
        $this->assertSame(Achievement::count(), count($response->json()));
    }

    public function test_a_member_sees_only_their_own_earned_achievements()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $achievement = Achievement::first();
        $user->userAchievements()->create(['achievement_id' => $achievement->id, 'earned_at' => now()]);

        $response = $this->actingAs($user)->getJson('/profile/achievements');

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame($achievement->id, $response->json('0.id'));
    }

    public function test_an_assigned_coach_can_view_a_members_earned_achievements()
    {
        $coach = User::factory()->create(['role_id' => Role::where('name', 'coach')->value('id')]);
        $member = User::factory()->create(['onboarding_completed_at' => now()]);
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $member->programs()->create(['program_id' => $program->id, 'status' => 'active', 'start_date' => today(), 'created_by' => 'ai']);
        app(CoachAssignmentService::class)->assign($coach, $member);

        $achievement = Achievement::first();
        $member->userAchievements()->create(['achievement_id' => $achievement->id, 'earned_at' => now()]);

        $this->actingAs($coach)->getJson("/profile/achievements?user_id={$member->id}")
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_a_non_assigned_user_cannot_view_another_members_earned_achievements()
    {
        $stranger = User::factory()->create(['onboarding_completed_at' => now()]);
        $member = User::factory()->create(['onboarding_completed_at' => now()]);

        $this->actingAs($stranger)->getJson("/profile/achievements?user_id={$member->id}")->assertForbidden();
    }
}
