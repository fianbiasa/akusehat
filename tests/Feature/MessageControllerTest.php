<?php

namespace Tests\Feature;

use App\Http\Controllers\ConversationController;
use App\Models\AiProvider;
use App\Models\Role;
use App\Models\User;
use App\Services\Coach\CoachAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MessageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_send_a_message_in_an_ai_assistant_conversation_and_gets_a_reply()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->actingAs($user)->post('/conversations');
        $conversation = $user->conversations()->first();

        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $user->aiSettings()->create(['provider_id' => $provider->id, 'model_id' => $provider->models()->first()->id, 'is_default' => true, 'api_key' => 'sk-test']);

        $replyJson = json_encode(['reply' => 'Halo! Ada yang bisa kubantu?']);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => $replyJson]]]], 200)]);

        $response = $this->actingAs($user)->postJson("/conversations/{$conversation->id}/messages", ['content' => 'Halo']);

        $response->assertOk();
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'sender_type' => 'user', 'content' => 'Halo']);
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'sender_type' => 'ai', 'content' => 'Halo! Ada yang bisa kubantu?']);
    }

    public function test_the_ai_reply_falls_back_gracefully_when_no_provider_is_configured()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->actingAs($user)->post('/conversations');
        $conversation = $user->conversations()->first();

        $response = $this->actingAs($user)->postJson("/conversations/{$conversation->id}/messages", ['content' => 'Halo']);

        $response->assertOk();
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'sender_type' => 'ai']);
    }

    public function test_coach_member_messages_do_not_trigger_an_ai_reply()
    {
        $coach = User::factory()->create(['role_id' => Role::where('name', 'coach')->value('id'), 'onboarding_completed_at' => now()]);
        $member = User::factory()->create(['onboarding_completed_at' => now()]);
        app(CoachAssignmentService::class)->assign($coach, $member);
        $conversation = ConversationController::findOrCreateCoachMemberConversation($coach, $member);

        $this->actingAs($member)->postJson("/conversations/{$conversation->id}/messages", ['content' => 'Halo Coach'])->assertOk();

        $this->assertSame(1, $conversation->messages()->count());
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'sender_type' => 'user']);
    }

    public function test_a_coach_sending_a_message_is_recorded_with_sender_type_coach()
    {
        $coach = User::factory()->create(['role_id' => Role::where('name', 'coach')->value('id'), 'onboarding_completed_at' => now()]);
        $member = User::factory()->create(['onboarding_completed_at' => now()]);
        app(CoachAssignmentService::class)->assign($coach, $member);
        $conversation = ConversationController::findOrCreateCoachMemberConversation($coach, $member);

        $this->actingAs($coach)->postJson("/conversations/{$conversation->id}/messages", ['content' => 'Halo!'])->assertOk();

        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'sender_type' => 'coach', 'sender_id' => $coach->id]);
    }

    public function test_a_third_party_cannot_send_a_message_into_someone_elses_conversation()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $intruder = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->actingAs($user)->post('/conversations');
        $conversation = $user->conversations()->first();

        $this->actingAs($intruder)->postJson("/conversations/{$conversation->id}/messages", ['content' => 'x'])->assertForbidden();
    }

    public function test_marking_a_conversation_read_updates_unread_messages_from_others()
    {
        $coach = User::factory()->create(['role_id' => Role::where('name', 'coach')->value('id'), 'onboarding_completed_at' => now()]);
        $member = User::factory()->create(['onboarding_completed_at' => now()]);
        app(CoachAssignmentService::class)->assign($coach, $member);
        $conversation = ConversationController::findOrCreateCoachMemberConversation($coach, $member);
        $message = $conversation->messages()->create(['sender_type' => 'coach', 'sender_id' => $coach->id, 'content' => 'Hi', 'created_at' => now()]);

        $this->actingAs($member)->patchJson("/conversations/{$conversation->id}/read")->assertOk();

        $this->assertNotNull($message->fresh()->read_at);
    }
}
