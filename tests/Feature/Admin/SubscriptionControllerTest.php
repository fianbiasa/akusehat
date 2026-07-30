<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
    }

    public function test_an_admin_can_list_all_subscriptions()
    {
        $admin = $this->admin();
        $member = User::factory()->create();
        app(SubscriptionService::class)->subscribe($member, Plan::where('slug', 'premium-bulanan')->firstOrFail());

        $response = $this->actingAs($admin)->get('/admin/subscriptions');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('subscriptions.data', 1));
    }

    public function test_the_list_can_be_filtered_by_status()
    {
        $admin = $this->admin();
        $active = User::factory()->create();
        $cancelled = User::factory()->create();
        $service = app(SubscriptionService::class);
        $plan = Plan::where('slug', 'premium-bulanan')->firstOrFail();
        $service->subscribe($active, $plan);
        $sub = $service->subscribe($cancelled, $plan);
        $sub->update(['status' => 'expired']);

        $response = $this->actingAs($admin)->get('/admin/subscriptions?status=active');

        $response->assertInertia(fn ($page) => $page->has('subscriptions.data', 1));
    }

    public function test_a_non_admin_cannot_view_subscriptions()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/subscriptions')->assertForbidden();
    }
}
