<?php

namespace App\Observers;

use App\Models\BodyMeasurement;
use App\Services\HealthProfileService;

class BodyMeasurementObserver
{
    public function __construct(private HealthProfileService $healthProfileService) {}

    public function created(BodyMeasurement $measurement): void
    {
        if ($measurement->weight_kg) {
            $this->healthProfileService->recalculate($measurement->user);
        }
    }
}
