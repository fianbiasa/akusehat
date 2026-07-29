<?php

namespace App\Observers;

use App\Models\LifestyleProfile;
use App\Services\HealthProfileService;

class LifestyleProfileObserver
{
    public function __construct(private HealthProfileService $healthProfileService) {}

    public function updated(LifestyleProfile $profile): void
    {
        if ($profile->wasChanged('activity_level')) {
            $this->healthProfileService->recalculate($profile->user);
        }
    }
}
