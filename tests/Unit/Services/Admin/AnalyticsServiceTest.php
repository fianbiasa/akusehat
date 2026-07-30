<?php

namespace Tests\Unit\Services\Admin;

use App\Models\AiProvider;
use App\Models\AiRequestLog;
use App\Models\HealthScore;
use App\Models\Program;
use App\Models\User;
use App\Models\UserProgram;
use App\Services\Admin\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_users_counts_only_active_status_with_recent_login()
    {
        User::factory()->create(['status' => 'active', 'last_login_at' => now()->subDays(5)]);
        User::factory()->create(['status' => 'active', 'last_login_at' => now()->subDays(40)]);
        User::factory()->create(['status' => 'suspended', 'last_login_at' => now()->subDays(1)]);
        User::factory()->create(['status' => 'active', 'last_login_at' => null]);

        $this->assertSame(1, (new AnalyticsService)->activeUsers());
    }

    public function test_program_completion_percent_is_zero_with_no_programs()
    {
        $this->assertSame(0.0, (new AnalyticsService)->programCompletionPercent());
    }

    public function test_program_completion_percent_divides_completed_by_total()
    {
        $program = Program::first();

        UserProgram::create(['user_id' => User::factory()->create()->id, 'program_id' => $program->id, 'status' => 'completed', 'start_date' => now()->subDays(90)]);
        UserProgram::create(['user_id' => User::factory()->create()->id, 'program_id' => $program->id, 'status' => 'active', 'start_date' => now()->subDays(10)]);

        $this->assertSame(50.0, (new AnalyticsService)->programCompletionPercent());
    }

    public function test_average_health_score_uses_only_each_users_latest_score()
    {
        $user = User::factory()->create();

        HealthScore::create(['user_id' => $user->id, 'scored_at' => now()->subDays(2)->toDateString(), 'score' => 40, 'breakdown' => []]);
        HealthScore::create(['user_id' => $user->id, 'scored_at' => now()->subDays(1)->toDateString(), 'score' => 80, 'breakdown' => []]);

        $this->assertSame(80.0, (new AnalyticsService)->averageHealthScore());
    }

    public function test_average_health_score_is_null_with_no_scores()
    {
        $this->assertNull((new AnalyticsService)->averageHealthScore());
    }

    public function test_ai_cost_30d_sums_only_recent_requests()
    {
        $provider = AiProvider::first();
        $model = $provider->models()->first();

        AiRequestLog::create(['provider_id' => $provider->id, 'model_id' => $model->id, 'purpose' => 'test', 'status' => 'success', 'estimated_cost' => 1.5, 'created_at' => now()->subDays(5)]);
        AiRequestLog::create(['provider_id' => $provider->id, 'model_id' => $model->id, 'purpose' => 'test', 'status' => 'success', 'estimated_cost' => 2.5, 'created_at' => now()->subDays(45)]);

        $this->assertSame(1.5, (new AnalyticsService)->aiCost30d());
    }

    public function test_ai_cost_by_provider_returns_percentages_that_sum_to_100()
    {
        $openai = AiProvider::where('slug', 'openai')->first();
        $claude = AiProvider::where('slug', 'claude')->first();

        AiRequestLog::create(['provider_id' => $openai->id, 'model_id' => $openai->models()->first()->id, 'purpose' => 'test', 'status' => 'success', 'estimated_cost' => 3.0, 'created_at' => now()]);
        AiRequestLog::create(['provider_id' => $claude->id, 'model_id' => $claude->models()->first()->id, 'purpose' => 'test', 'status' => 'success', 'estimated_cost' => 1.0, 'created_at' => now()]);

        $rows = (new AnalyticsService)->aiCostByProvider30d();

        $this->assertSame('OpenAI', $rows[0]['provider']);
        $this->assertSame(75.0, $rows[0]['percent']);
        $this->assertSame(25.0, $rows[1]['percent']);
    }
}
