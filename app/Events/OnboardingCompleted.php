<?php

namespace App\Events;

use App\Models\OnboardingSession;
use Illuminate\Foundation\Events\Dispatchable;

class OnboardingCompleted
{
    use Dispatchable;

    public function __construct(public OnboardingSession $session) {}
}
