<?php

namespace Tests\Feature\Program;

use App\Jobs\GenerateWeeklyReviewJob;
use App\Models\AiProvider;
use App\Models\Program;
use App\Models\User;
use App\Services\AI\AIGatewayService;
use App\Services\Program\RecommendationApplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerateWeeklyReviewJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_the_ai_review_and_applies_adjustments()
    {
        $user = User::factory()->create();
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $userProgram = $user->programs()->create([
            'program_id' => $program->id, 'status' => 'active',
            'start_date' => today()->subDays(7), 'end_date' => today()->addDays(82), 'created_by' => 'ai',
        ]);
        $weeklyPlan = $userProgram->weeklyPlans()->create([
            'week_number' => 1, 'start_date' => today()->subDays(7), 'end_date' => today()->subDay(),
            'generated_by' => 'rule_engine',
        ]);

        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $user->aiSettings()->create(['provider_id' => $provider->id, 'model_id' => $provider->models()->first()->id, 'is_default' => true, 'api_key' => 'sk-test']);

        $reviewJson = json_encode([
            'summary' => 'Progress steady.',
            'trend' => 'improving',
            'adjustments' => [
                ['type' => 'habit', 'detail' => 'Tambahkan jalan kaki sore.', 'auto_applicable' => true],
            ],
            'motivation' => 'Terus semangat!',
        ]);

        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => $reviewJson]]]], 200)]);

        (new GenerateWeeklyReviewJob($weeklyPlan))->handle(app(AIGatewayService::class), app(RecommendationApplierService::class));

        $weeklyPlan->refresh();
        $this->assertSame('Progress steady.', $weeklyPlan->ai_summary);
        $this->assertSame('ai', $weeklyPlan->generated_by);
        $this->assertDatabaseHas('ai_recommendations', ['user_program_id' => $userProgram->id, 'type' => 'habit', 'status' => 'applied']);
    }

    public function test_it_falls_back_gracefully_when_no_ai_provider_is_configured()
    {
        $user = User::factory()->create();
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $userProgram = $user->programs()->create([
            'program_id' => $program->id, 'status' => 'active',
            'start_date' => today()->subDays(7), 'end_date' => today()->addDays(82), 'created_by' => 'ai',
        ]);
        $weeklyPlan = $userProgram->weeklyPlans()->create([
            'week_number' => 1, 'start_date' => today()->subDays(7), 'end_date' => today()->subDay(),
            'generated_by' => 'rule_engine',
        ]);

        (new GenerateWeeklyReviewJob($weeklyPlan))->handle(app(AIGatewayService::class), app(RecommendationApplierService::class));

        $weeklyPlan->refresh();
        $this->assertNotNull($weeklyPlan->ai_summary);
        $this->assertSame('rule_engine', $weeklyPlan->generated_by);
    }
}
