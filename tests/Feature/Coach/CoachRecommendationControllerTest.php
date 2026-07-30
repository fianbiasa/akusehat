<?php

namespace Tests\Feature\Coach;

use App\Models\Role;
use App\Models\User;
use App\Notifications\RecommendationReviewed;
use App\Services\Coach\CoachAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CoachRecommendationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function coach(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'coach')->value('id')]);
    }

    public function test_approving_a_recommendation_sets_status_applied_and_reviewed_by_and_notifies_the_member()
    {
        Notification::fake();
        $coach = $this->coach();
        $member = User::factory()->create();
        app(CoachAssignmentService::class)->assign($coach, $member);
        $recommendation = $member->aiRecommendations()->create([
            'type' => 'meal_adjustment', 'content' => ['detail' => 'x'], 'status' => 'pending',
        ]);

        $this->actingAs($coach)->post("/coach/recommendations/{$recommendation->id}/approve")->assertSessionHasNoErrors();

        $recommendation->refresh();
        $this->assertSame('applied', $recommendation->status);
        $this->assertSame($coach->id, $recommendation->reviewed_by);
        $this->assertNotNull($recommendation->applied_at);
        Notification::assertSentTo($member, RecommendationReviewed::class);
    }

    public function test_rejecting_a_recommendation_sets_status_rejected_and_stores_the_reason()
    {
        Notification::fake();
        $coach = $this->coach();
        $member = User::factory()->create();
        app(CoachAssignmentService::class)->assign($coach, $member);
        $recommendation = $member->aiRecommendations()->create([
            'type' => 'meal_adjustment', 'content' => ['detail' => 'x'], 'status' => 'pending',
        ]);

        $this->actingAs($coach)->post("/coach/recommendations/{$recommendation->id}/reject", [
            'reason' => 'Tidak sesuai kondisi member.',
        ])->assertSessionHasNoErrors();

        $recommendation->refresh();
        $this->assertSame('rejected', $recommendation->status);
        $this->assertSame('Tidak sesuai kondisi member.', $recommendation->content['rejection_reason']);
        Notification::assertSentTo($member, RecommendationReviewed::class);
    }

    public function test_a_coach_cannot_approve_a_recommendation_for_an_unassigned_member()
    {
        $coach = $this->coach();
        $member = User::factory()->create();
        $recommendation = $member->aiRecommendations()->create([
            'type' => 'meal_adjustment', 'content' => ['detail' => 'x'], 'status' => 'pending',
        ]);

        $this->actingAs($coach)->post("/coach/recommendations/{$recommendation->id}/approve")->assertForbidden();
    }
}
