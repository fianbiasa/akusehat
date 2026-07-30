<?php

namespace App\Events;

use App\Models\AiRecommendation;
use Illuminate\Foundation\Events\Dispatchable;

class AIRecommendationCreated
{
    use Dispatchable;

    public function __construct(public AiRecommendation $recommendation) {}
}
