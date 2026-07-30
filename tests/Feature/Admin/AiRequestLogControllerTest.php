<?php

namespace Tests\Feature\Admin;

use App\Models\AiModel;
use App\Models\AiRequestLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiRequestLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
    }

    private function makeLog(array $overrides = []): AiRequestLog
    {
        $model = AiModel::firstOrFail();

        return AiRequestLog::create(array_merge([
            'provider_id' => $model->provider_id,
            'model_id' => $model->id,
            'purpose' => 'meal_plan',
            'prompt_tokens' => 500,
            'completion_tokens' => 200,
            'estimated_cost' => 0.01,
            'latency_ms' => 1200,
            'status' => 'success',
            'created_at' => now(),
        ], $overrides));
    }

    public function test_an_admin_can_view_the_request_log_with_cost_stats()
    {
        $admin = $this->admin();
        $this->makeLog(['estimated_cost' => 0.02]);
        $this->makeLog(['estimated_cost' => 0.03, 'status' => 'error', 'error_message' => 'timeout']);

        $this->actingAs($admin)->get('/admin/ai/request-logs')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('logs.data', 2)
                ->where('stats.total_requests', 2)
                ->where('stats.success_rate', 50)
                ->where('stats.total_cost', 0.05)
            );
    }

    public function test_the_log_can_be_filtered_by_status()
    {
        $admin = $this->admin();
        $this->makeLog(['status' => 'success']);
        $this->makeLog(['status' => 'error', 'error_message' => 'boom']);

        $this->actingAs($admin)->get('/admin/ai/request-logs?status=error')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('logs.data', 1));
    }

    public function test_a_non_admin_cannot_view_the_request_log()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/ai/request-logs')->assertForbidden();
    }
}
