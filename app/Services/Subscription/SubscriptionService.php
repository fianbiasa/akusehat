<?php

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * v1 ships schema + gating only - no live payment gateway (PRD §6.3,
 * roadmap v1.1). subscribe() therefore simulates an instantly-successful
 * payment rather than redirecting to a real processor; `payments.provider`
 * has no "sandbox" enum value, so `midtrans` is used as the placeholder
 * with an obviously-fake `provider_reference`.
 */
class SubscriptionService
{
    /**
     * Every user always resolves to a subscription - lazily backfilling
     * onto the free "gratis" plan the first time it's needed (covers
     * both pre-Phase-11 existing users and a subscription that just
     * expired with nothing renewing it, since there's no live gateway
     * to auto-renew). New registrations also get one eagerly via
     * AssignDefaultPlan, so this fallback rarely actually fires.
     */
    public function currentSubscription(User $user): Subscription
    {
        return $user->activeSubscription()->first() ?? $this->assignDefaultPlan($user);
    }

    public function assignDefaultPlan(User $user): Subscription
    {
        $plan = Plan::where('slug', 'gratis')->firstOrFail();

        return $user->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);
    }

    public function withinProgramLimit(User $user): bool
    {
        $plan = $this->currentSubscription($user)->plan;

        return $user->activePrograms()->count() < $plan->max_programs;
    }

    public function hasCoachAccess(User $user): bool
    {
        return (bool) $this->currentSubscription($user)->plan->has_coach_access;
    }

    public function subscribe(User $user, Plan $plan): Subscription
    {
        return DB::transaction(function () use ($user, $plan) {
            $user->activeSubscription()->first()?->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            $endsAt = match ($plan->billing_cycle) {
                'monthly' => now()->addMonth(),
                'yearly' => now()->addYear(),
                'lifetime' => null,
            };

            $subscription = $user->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => $endsAt,
            ]);

            if ((float) $plan->price > 0) {
                $subscription->payments()->create([
                    'provider' => 'midtrans',
                    'provider_reference' => 'SANDBOX-'.strtoupper(Str::random(10)),
                    'amount' => $plan->price,
                    'currency' => 'IDR',
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            }

            return $subscription;
        });
    }

    /**
     * Cancel at period end (05-API-Specification.md §14) - access is
     * kept through `ends_at`; SubscriptionRenewalCheckJob flips the
     * status to `expired` once that date passes rather than revoking
     * immediately here.
     */
    public function cancel(Subscription $subscription): void
    {
        $subscription->update(['cancelled_at' => now()]);
    }
}
