<?php

namespace Database\Seeders;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\AI\Providers\ClaudeProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\GroqProvider;
use App\Services\AI\Providers\LMStudioProvider;
use App\Services\AI\Providers\OllamaProvider;
use App\Services\AI\Providers\OpenAIProvider;
use Illuminate\Database\Seeder;

class AiProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'name' => 'OpenAI', 'slug' => 'openai', 'type' => 'cloud',
                'driver_class' => OpenAIProvider::class,
                'models' => [
                    ['name' => 'GPT-5.5', 'model_key' => 'gpt-5.5', 'context_length' => 200000, 'input_cost_per_1k' => 0.003, 'output_cost_per_1k' => 0.012],
                ],
            ],
            [
                'name' => 'Claude', 'slug' => 'claude', 'type' => 'cloud',
                'driver_class' => ClaudeProvider::class,
                'models' => [
                    ['name' => 'Claude Sonnet', 'model_key' => 'claude-sonnet-5', 'context_length' => 200000, 'input_cost_per_1k' => 0.003, 'output_cost_per_1k' => 0.015],
                ],
            ],
            [
                'name' => 'Groq', 'slug' => 'groq', 'type' => 'cloud',
                'driver_class' => GroqProvider::class,
                'models' => [
                    ['name' => 'Llama 4', 'model_key' => 'llama-4-70b', 'context_length' => 128000, 'input_cost_per_1k' => 0.0005, 'output_cost_per_1k' => 0.0008, 'supports_json_mode' => false],
                ],
            ],
            [
                'name' => 'Gemini', 'slug' => 'gemini', 'type' => 'cloud',
                'driver_class' => GeminiProvider::class,
                'models' => [
                    ['name' => 'Gemini 2.5', 'model_key' => 'gemini-2.5-flash', 'context_length' => 1000000, 'input_cost_per_1k' => 0.00015, 'output_cost_per_1k' => 0.0006],
                ],
            ],
            [
                'name' => 'Ollama', 'slug' => 'ollama', 'type' => 'local',
                'driver_class' => OllamaProvider::class,
                'models' => [
                    ['name' => 'Llama 3 8B', 'model_key' => 'llama3:8b', 'context_length' => 8192, 'input_cost_per_1k' => 0, 'output_cost_per_1k' => 0],
                ],
            ],
            [
                'name' => 'LM Studio', 'slug' => 'lm-studio', 'type' => 'local',
                'driver_class' => LMStudioProvider::class,
                'models' => [
                    ['name' => 'Local Model', 'model_key' => 'local-model', 'context_length' => 8192, 'input_cost_per_1k' => 0, 'output_cost_per_1k' => 0],
                ],
            ],
        ];

        foreach ($providers as $providerData) {
            $models = $providerData['models'];
            unset($providerData['models']);

            $provider = AiProvider::updateOrCreate(['slug' => $providerData['slug']], $providerData);

            foreach ($models as $model) {
                AiModel::updateOrCreate(
                    ['provider_id' => $provider->id, 'model_key' => $model['model_key']],
                    $model,
                );
            }
        }
    }
}
