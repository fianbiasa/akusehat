<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SubscriptionRenewalCheckJob;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\SubscriptionExpired;
use App\Notifications\SubscriptionExpiringSoon;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubscriptionRenewalCheckJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_expires_a_subscription_whose_period_has_passed_and_notifies_the_user()
    {
        Notification::fake();

        $user = User::factory()->create();
        $subscription = app(SubscriptionService::class)->subscribe($user, Plan::where('slug', 'premium-bulanan')->firstOrFail());
        $subscription->update(['ends_at' => now()->subDay()]);

        app(SubscriptionRenewalCheckJob::class)->handle();

        $this->assertSame('expired', $subscription->fresh()->status);
        Notification::assertSentTo($user, SubscriptionExpired::class);
    }

    public function test_it_flags_a_subscription_expiring_in_exactly_7_days()
    {
        Notification::fake();

        $user = User::factory()->create();
        $subscription = app(SubscriptionService::class)->subscribe($user, Plan::where('slug', 'premium-bulanan')->firstOrFail());
        $subscription->update(['ends_at' => now()->addDays(7)->setTime(12, 0)]);

        app(SubscriptionRenewalCheckJob::class)->handle();

        $this->assertSame('active', $subscription->fresh()->status);
        Notification::assertSentTo($user, SubscriptionExpiringSoon::class);
    }

    public function test_it_leaves_a_subscription_expiring_in_20_days_alone()
    {
        Notification::fake();

        $user = User::factory()->create();
        $subscription = app(SubscriptionService::class)->subscribe($user, Plan::where('slug', 'premium-bulanan')->firstOrFail());
        $subscription->update(['ends_at' => now()->addDays(20)]);

        app(SubscriptionRenewalCheckJob::class)->handle();

        $this->assertSame('active', $subscription->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_a_user_falls_back_onto_the_free_plan_after_their_subscription_expires()
    {
        Notification::fake();

        $user = User::factory()->create();
        $subscription = app(SubscriptionService::class)->subscribe($user, Plan::where('slug', 'premium-bulanan')->firstOrFail());
        $subscription->update(['ends_at' => now()->subDay()]);

        app(SubscriptionRenewalCheckJob::class)->handle();

        $current = app(SubscriptionService::class)->currentSubscription($user);
        $this->assertSame('gratis', $current->plan->slug);
    }
}
