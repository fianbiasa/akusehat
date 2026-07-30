<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Notifications\SubscriptionExpired;
use App\Notifications\SubscriptionExpiringSoon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Scheduled daily (04-Architecture.md §6). No live payment gateway in
 * v1 (PRD §6.3), so there is no actual renewal to attempt here - this
 * only (a) transitions subscriptions whose period has passed to
 * `expired`, and (b) flags ones expiring soon. Once expired, the user
 * naturally falls back onto the "gratis" plan the next time
 * SubscriptionService::currentSubscription() is called, rather than
 * this job inserting a new row itself.
 */
class SubscriptionRenewalCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Subscription::where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->with('user', 'plan')
            ->each(function (Subscription $subscription) {
                $subscription->update(['status' => 'expired']);
                $subscription->user->notify(new SubscriptionExpired($subscription));
            });

        Subscription::where('status', 'active')
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now()->addDays(7)->startOfDay(), now()->addDays(7)->endOfDay()])
            ->with('user', 'plan')
            ->each(function (Subscription $subscription) {
                $subscription->user->notify(new SubscriptionExpiringSoon($subscription));
            });
    }
}
