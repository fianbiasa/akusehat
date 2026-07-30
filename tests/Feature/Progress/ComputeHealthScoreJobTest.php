<?php

namespace Tests\Feature\Progress;

use App\Jobs\ComputeHealthScoreJob;
use App\Models\AiProvider;
use App\Models\User;
use App\Services\AI\AIGatewayService;
use App\Services\HealthScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ComputeHealthScoreJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_computes_and_persists_a_score_with_a_fallback_explanation_when_ai_is_unavailable()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $user->healthProfile()->create(['gender' => 'male', 'date_of_birth' => now()->subYears(30), 'height_cm' => 170, 'initial_weight_kg' => 70, 'bmi' => 22]);

        (new ComputeHealthScoreJob)->handle(app(HealthScoreService::class), app(AIGatewayService::class));

        $this->assertDatabaseHas('health_scores', ['user_id' => $user->id, 'scored_at' => today()->toDateString()]);
        $this->assertNotNull($user->healthScores()->first()->explanation);
    }

    public function test_running_it_twice_in_one_day_updates_rather_than_duplicates()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $user->healthProfile()->create(['gender' => 'male', 'date_of_birth' => now()->subYears(30), 'height_cm' => 170, 'initial_weight_kg' => 70, 'bmi' => 22]);

        $service = app(HealthScoreService::class);
        $gateway = app(AIGatewayService::class);

        (new ComputeHealthScoreJob)->handle($service, $gateway);
        $firstId = $user->healthScores()->first()->id;
        (new ComputeHealthScoreJob)->handle($service, $gateway);

        $this->assertSame(1, $user->healthScores()->count());
        $this->assertSame($firstId, $user->healthScores()->first()->id);
    }

    public function test_it_uses_the_ai_generated_explanation_when_a_provider_is_configured()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $user->healthProfile()->create(['gender' => 'male', 'date_of_birth' => now()->subYears(30), 'height_cm' => 170, 'initial_weight_kg' => 70, 'bmi' => 22]);

        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $user->aiSettings()->create(['provider_id' => $provider->id, 'model_id' => $provider->models()->first()->id, 'is_default' => true, 'api_key' => 'sk-test']);

        $explainJson = json_encode(['summary' => 'ok', 'explanation' => 'Penjelasan dari AI.', 'key_factors' => []]);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => $explainJson]]]], 200)]);

        (new ComputeHealthScoreJob)->handle(app(HealthScoreService::class), app(AIGatewayService::class));

        $this->assertSame('Penjelasan dari AI.', $user->healthScores()->first()->explanation);
    }
}
