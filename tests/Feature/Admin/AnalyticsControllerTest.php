<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_view_the_analytics_dashboard()
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);

        $this->actingAs($admin)->get('/admin/analytics')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('summary.active_users')
                ->has('summary.program_completion_percent')
                ->has('summary.ai_cost_by_provider'));
    }

    public function test_a_member_cannot_view_the_analytics_dashboard()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/analytics')->assertForbidden();
    }

    public function test_a_coach_cannot_view_the_analytics_dashboard()
    {
        $coach = User::factory()->create(['role_id' => Role::where('name', 'coach')->value('id')]);

        $this->actingAs($coach)->get('/admin/analytics')->assertForbidden();
    }
}
