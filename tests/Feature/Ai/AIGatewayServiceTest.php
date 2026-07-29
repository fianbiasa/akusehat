<?php

namespace Tests\Feature\Ai;

use App\Models\AiProvider;
use App\Models\AiRecommendation;
use App\Models\AiRequestLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AI\AIGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIGatewayServiceTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'member')->value('id')]);
        $user->healthProfile()->create(['gender' => 'male', 'date_of_birth' => '1990-01-01', 'height_cm' => 170, 'initial_weight_kg' => 70]);
        $user->lifestyleProfile()->create(['activity_level' => 'light']);

        return $user->fresh();
    }

    public function test_a_successful_call_is_logged_and_returns_the_validated_data()
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'summary' => 's', 'trend' => 'improving', 'adjustments' => [], 'motivation' => 'm',
            ])]]],
        ], 200)]);

        $user = $this->member();
        $provider = AiProvider::where('slug', 'openai')->first();
        $user->aiSettings()->create(['provider_id' => $provider->id, 'model_id' => $provider->models()->first()->id, 'api_key' => 'sk-test', 'is_default' => true]);

        $result = app(AIGatewayService::class)->send($user, 'weeklyReview', 'weekly_review');

        $this->assertSame('improving', $result['trend']);
        $this->assertDatabaseHas('ai_request_logs', ['user_id' => $user->id, 'provider_id' => $provider->id, 'status' => 'success']);
    }

    public function test_no_configured_provider_falls_back_to_rule_engine_without_logging()
    {
        $user = $this->member();

        $result = app(AIGatewayService::class)->send($user, 'weeklyReview', 'weekly_review');

        $this->assertTrue($result['ai_unavailable']);
        $this->assertSame('no_provider_configured', $result['reason']);
        $this->assertSame(0, AiRequestLog::count());
    }

    public function test_a_failing_primary_fails_over_to_the_secondary_and_logs_both_attempts()
    {
        Http::fake([
            'api.openai.com/*' => Http::response('Server error', 500),
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => json_encode(['summary' => 's', 'trend' => 'stagnant', 'adjustments' => [], 'motivation' => 'm'])]],
            ], 200),
        ]);

        $user = $this->member();
        $openai = AiProvider::where('slug', 'openai')->first();
        $claude = AiProvider::where('slug', 'claude')->first();
        $user->aiSettings()->create(['provider_id' => $openai->id, 'model_id' => $openai->models()->first()->id, 'api_key' => 'sk-test', 'is_default' => true]);
        $user->aiSettings()->create(['provider_id' => $claude->id, 'model_id' => $claude->models()->first()->id, 'api_key' => 'sk-ant', 'is_default' => false]);

        $result = app(AIGatewayService::class)->send($user, 'weeklyReview', 'weekly_review');

        $this->assertSame('stagnant', $result['trend']);
        $this->assertDatabaseHas('ai_request_logs', ['user_id' => $user->id, 'provider_id' => $openai->id, 'status' => 'error']);
        $this->assertDatabaseHas('ai_request_logs', ['user_id' => $user->id, 'provider_id' => $claude->id, 'status' => 'success']);
    }

    public function test_both_providers_failing_falls_back_to_rule_engine()
    {
        Http::fake(['*' => Http::response('Server error', 500)]);

        $user = $this->member();
        $openai = AiProvider::where('slug', 'openai')->first();
        $claude = AiProvider::where('slug', 'claude')->first();
        $user->aiSettings()->create(['provider_id' => $openai->id, 'model_id' => $openai->models()->first()->id, 'api_key' => 'sk-test', 'is_default' => true]);
        $user->aiSettings()->create(['provider_id' => $claude->id, 'model_id' => $claude->models()->first()->id, 'api_key' => 'sk-ant', 'is_default' => false]);

        $result = app(AIGatewayService::class)->send($user, 'weeklyReview', 'weekly_review');

        $this->assertTrue($result['ai_unavailable']);
        $this->assertSame(2, AiRequestLog::where('status', 'error')->count());
    }

    public function test_a_persistently_invalid_response_creates_an_audit_recommendation()
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['nonsense' => true])]]],
        ], 200)]);

        $user = $this->member();
        $provider = AiProvider::where('slug', 'openai')->first();
        $user->aiSettings()->create(['provider_id' => $provider->id, 'model_id' => $provider->models()->first()->id, 'api_key' => 'sk-test', 'is_default' => true]);

        app(AIGatewayService::class)->send($user, 'weeklyReview', 'weekly_review');

        $this->assertDatabaseHas('ai_request_logs', ['user_id' => $user->id, 'status' => 'invalid_json']);
        $this->assertSame(1, AiRecommendation::where('user_id', $user->id)->where('status', 'expired')->count());
    }
}
