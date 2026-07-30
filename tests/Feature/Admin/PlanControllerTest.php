<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
    }

    public function test_an_admin_can_view_the_plans_list()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/plans')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('plans', Plan::count()));
    }

    public function test_an_admin_can_create_a_plan()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/plans', [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'price' => 500000,
            'billing_cycle' => 'monthly',
            'features' => ['Semua fitur Premium', 'Dukungan prioritas'],
            'max_programs' => 10,
            'has_coach_access' => true,
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('plans', ['slug' => 'enterprise', 'max_programs' => 10]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'plan.created']);
    }

    public function test_an_admin_can_update_a_plan()
    {
        $admin = $this->admin();
        $plan = Plan::where('slug', 'gratis')->firstOrFail();

        $this->actingAs($admin)->patch("/admin/plans/{$plan->id}", [
            'name' => 'Gratis',
            'price' => 0,
            'billing_cycle' => 'monthly',
            'max_programs' => 2,
            'has_coach_access' => false,
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $plan->fresh()->max_programs);
        $this->assertDatabaseHas('activity_logs', ['action' => 'plan.updated']);
    }

    public function test_a_non_admin_cannot_manage_plans()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/plans')->assertForbidden();
    }
}
