<?php

namespace App\Listeners;

use App\Events\CheckInSubmitted;
use App\Services\Program\AIMemoryService;

class FeedAIMemoryOnCheckIn
{
    public function __construct(private AIMemoryService $aiMemoryService) {}

    public function handle(CheckInSubmitted $event): void
    {
        $this->aiMemoryService->scan($event->user);
    }
}
