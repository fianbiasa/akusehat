<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\Conversation;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use App\Models\UserProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_rate_limited_after_5_attempts_per_minute()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [
                'name' => 'Test', 'email' => "test{$i}@example.com", 'password' => 'password', 'password_confirmation' => 'password',
            ]);
            $this->post('/logout');
        }

        $response = $this->post('/register', [
            'name' => 'Test', 'email' => 'test-overflow@example.com', 'password' => 'password', 'password_confirmation' => 'password',
        ]);

        $response->assertStatus(429);
    }

    public function test_forgot_password_is_rate_limited_after_3_attempts_per_minute()
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->post('/forgot-password', ['email' => $user->email]);
        }

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertStatus(429);
    }

    public function test_ai_settings_test_is_rate_limited_after_10_attempts_per_minute()
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"ok":true}']]]], 200)]);

        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $setting = $user->aiSettings()->create(['provider_id' => $provider->id, 'model_id' => $provider->models()->first()->id, 'api_key' => 'sk-test', 'is_default' => true]);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson("/ai/settings/{$setting->id}/test");
        }

        $response = $this->actingAs($user)->postJson("/ai/settings/{$setting->id}/test");

        $response->assertStatus(429);
    }

    public function test_chat_messages_are_rate_limited_after_20_per_minute()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $conversation = Conversation::create(['type' => 'ai_assistant', 'user_id' => $user->id]);

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($user)->postJson("/conversations/{$conversation->id}/messages", ['content' => "msg {$i}"]);
        }

        $response = $this->actingAs($user)->postJson("/conversations/{$conversation->id}/messages", ['content' => 'overflow']);

        $response->assertStatus(429);
    }

    public function test_program_regeneration_is_rate_limited_after_5_per_minute()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $userProgram = $user->programs()->create([
            'program_id' => $program->id, 'status' => 'active', 'start_date' => today(), 'end_date' => today()->addDays(89), 'created_by' => 'ai',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->post("/user-programs/{$userProgram->id}/regenerate");
        }

        $response = $this->actingAs($user)->post("/user-programs/{$userProgram->id}/regenerate");

        $response->assertStatus(429);
    }
}
