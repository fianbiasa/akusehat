<?php

namespace Tests\Unit\Services\Subscription;

use App\Models\Plan;
use App\Models\Program;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    public function test_current_subscription_lazily_backfills_the_free_plan_when_none_exists()
    {
        $user = User::factory()->create();

        $subscription = $this->service()->currentSubscription($user);

        $this->assertSame('gratis', $subscription->plan->slug);
        $this->assertSame('active', $subscription->status);
        $this->assertDatabaseHas('subscriptions', ['user_id' => $user->id, 'plan_id' => $subscription->plan_id]);
    }

    public function test_current_subscription_does_not_create_a_second_row_once_one_exists()
    {
        $user = User::factory()->create();

        $first = $this->service()->currentSubscription($user);
        $second = $this->service()->currentSubscription($user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $user->subscriptions()->count());
    }

    public function test_within_program_limit_respects_the_plans_max_programs()
    {
        $user = User::factory()->create();
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();

        $this->assertTrue($this->service()->withinProgramLimit($user));

        $user->programs()->create(['program_id' => $program->id, 'status' => 'active', 'start_date' => today(), 'created_by' => 'ai']);

        $this->assertFalse($this->service()->withinProgramLimit($user));
    }

    public function test_has_coach_access_reflects_the_current_plan()
    {
        $user = User::factory()->create();

        $this->assertFalse($this->service()->hasCoachAccess($user));

        $this->service()->subscribe($user, Plan::where('slug', 'premium-bulanan')->firstOrFail());

        $this->assertTrue($this->service()->hasCoachAccess($user));
    }

    public function test_subscribing_creates_a_sandboxed_paid_payment_and_sets_ends_at_from_the_billing_cycle()
    {
        $user = User::factory()->create();
        $plan = Plan::where('slug', 'premium-bulanan')->firstOrFail();

        $subscription = $this->service()->subscribe($user, $plan);

        $this->assertSame('active', $subscription->status);
        $this->assertNotNull($subscription->ends_at);
        $this->assertTrue($subscription->ends_at->isBetween(now()->addDays(27), now()->addDays(32)));
        $this->assertSame(1, $subscription->payments()->where('status', 'paid')->count());
    }

    public function test_subscribing_to_the_free_plan_does_not_create_a_payment_row()
    {
        $user = User::factory()->create();
        $plan = Plan::where('slug', 'gratis')->firstOrFail();

        $subscription = $this->service()->subscribe($user, $plan);

        $this->assertSame(0, $subscription->payments()->count());
    }

    public function test_subscribing_again_cancels_the_previous_active_subscription()
    {
        $user = User::factory()->create();
        $monthly = Plan::where('slug', 'premium-bulanan')->firstOrFail();
        $yearly = Plan::where('slug', 'premium-tahunan')->firstOrFail();

        $first = $this->service()->subscribe($user, $monthly);
        $this->service()->subscribe($user, $yearly);

        $this->assertSame('cancelled', $first->fresh()->status);
        $this->assertSame(2, $user->subscriptions()->count());
    }

    public function test_cancel_marks_cancelled_at_without_changing_status_or_ends_at()
    {
        $user = User::factory()->create();
        $subscription = $this->service()->subscribe($user, Plan::where('slug', 'premium-bulanan')->firstOrFail());
        $originalEndsAt = $subscription->ends_at;

        $this->service()->cancel($subscription);

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertTrue($subscription->ends_at->equalTo($originalEndsAt));
    }
}
