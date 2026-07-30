<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalog_is_reachable_without_auth()
    {
        $response = $this->getJson('/plans');

        $response->assertOk();
        $this->assertSame(Plan::where('is_active', true)->count(), count($response->json()));
    }

    public function test_a_member_can_view_their_subscription_page()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $this->actingAs($user)->get('/subscription')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('subscription.plan.slug', 'gratis')
                ->where('usage.max_programs', 1));
    }

    public function test_a_member_can_subscribe_to_a_paid_plan()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $plan = Plan::where('slug', 'premium-bulanan')->firstOrFail();

        $this->actingAs($user)->post('/subscription/subscribe', ['plan_id' => $plan->id])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('subscriptions', ['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active']);
        $this->assertDatabaseHas('payments', ['status' => 'paid']);
    }

    public function test_a_member_can_cancel_a_paid_subscription()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        app(SubscriptionService::class)->subscribe($user, Plan::where('slug', 'premium-bulanan')->firstOrFail());

        $this->actingAs($user)->post('/subscription/cancel')->assertSessionHasNoErrors();

        $this->assertNotNull($user->activeSubscription()->first()->cancelled_at);
    }

    public function test_a_member_cannot_cancel_the_free_plan()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $this->actingAs($user)->post('/subscription/cancel')->assertStatus(422);
    }

    public function test_a_member_cannot_cancel_an_already_cancelled_subscription_twice()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        app(SubscriptionService::class)->subscribe($user, Plan::where('slug', 'premium-bulanan')->firstOrFail());

        $this->actingAs($user)->post('/subscription/cancel')->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/subscription/cancel')->assertStatus(422);
    }

    public function test_a_member_sees_only_their_own_payment_history()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $stranger = User::factory()->create();
        app(SubscriptionService::class)->subscribe($user, Plan::where('slug', 'premium-bulanan')->firstOrFail());
        app(SubscriptionService::class)->subscribe($stranger, Plan::where('slug', 'premium-bulanan')->firstOrFail());

        $response = $this->actingAs($user)->getJson('/subscription/payments');

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }
}
