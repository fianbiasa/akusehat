<?php

namespace App\Services\AI;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\AI\Contracts\AIProviderInterface;
use RuntimeException;

/**
 * Resolves ai_providers.driver_class into a concrete AIProviderInterface
 * instance. Adding a 7th provider is: implement the interface, add an
 * ai_providers row pointing at the class, no other code changes
 * (docs/06-AI-Provider-Interface.md §3).
 */
class AIProviderFactory
{
    public function make(AiProvider $provider, AiModel $model, ?string $apiKey, float $temperature = 0.7): AIProviderInterface
    {
        $driverClass = $provider->driver_class;

        if (! class_exists($driverClass) || ! is_a($driverClass, AIProviderInterface::class, true)) {
            throw new RuntimeException("AI provider driver [{$driverClass}] does not exist or does not implement AIProviderInterface.");
        }

        return new $driverClass(
            apiKey: $apiKey,
            baseUrl: $provider->base_url,
            modelKey: $model->model_key,
            temperature: $temperature,
        );
    }
}
