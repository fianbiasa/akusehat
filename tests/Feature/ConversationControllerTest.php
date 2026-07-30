<?php

namespace Tests\Feature;

use App\Http\Controllers\ConversationController;
use App\Models\Role;
use App\Models\User;
use App\Services\Coach\CoachAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedMember(): User
    {
        return User::factory()->create(['onboarding_completed_at' => now()]);
    }

    private function coach(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'coach')->value('id'), 'onboarding_completed_at' => now()]);
    }

    public function test_starting_an_ai_assistant_conversation_creates_and_returns_it()
    {
        $user = $this->onboardedMember();

        $response = $this->actingAs($user)->post('/conversations');

        $this->assertDatabaseHas('conversations', ['user_id' => $user->id, 'type' => 'ai_assistant']);
        $response->assertRedirect();
    }

    public function test_starting_it_twice_reuses_the_same_conversation()
    {
        $user = $this->onboardedMember();

        $this->actingAs($user)->post('/conversations');
        $this->actingAs($user)->post('/conversations');

        $this->assertSame(1, $user->conversations()->where('type', 'ai_assistant')->count());
    }

    public function test_a_coach_and_member_can_both_access_their_shared_conversation()
    {
        $coach = $this->coach();
        $member = $this->onboardedMember();
        app(CoachAssignmentService::class)->assign($coach, $member);
        $conversation = ConversationController::findOrCreateCoachMemberConversation($coach, $member);

        $this->actingAs($coach)->get("/conversations/{$conversation->id}")->assertOk();
        $this->actingAs($member)->get("/conversations/{$conversation->id}")->assertOk();
    }

    public function test_a_third_party_cannot_access_someone_elses_conversation()
    {
        $user = $this->onboardedMember();
        $intruder = $this->onboardedMember();
        $this->actingAs($user)->post('/conversations');
        $conversation = $user->conversations()->first();

        $this->actingAs($intruder)->get("/conversations/{$conversation->id}")->assertForbidden();
    }

    public function test_finding_or_creating_a_coach_member_conversation_is_idempotent()
    {
        $coach = $this->coach();
        $member = $this->onboardedMember();

        $first = ConversationController::findOrCreateCoachMemberConversation($coach, $member);
        $second = ConversationController::findOrCreateCoachMemberConversation($coach, $member);

        $this->assertSame($first->id, $second->id);
    }
}
