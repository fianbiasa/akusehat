<?php

namespace App\Listeners;

use App\Services\Subscription\SubscriptionService;
use Illuminate\Auth\Events\Registered;

class AssignDefaultPlan
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function handle(Registered $event): void
    {
        $this->subscriptions->assignDefaultPlan($event->user);
    }
}
