<?php

namespace App\Services\AI\Providers;

class OpenAIProvider extends AbstractHttpProvider
{
    protected function completeChat(array $messages, array $context): array
    {
        try {
            $response = $this->http()
                ->withToken($this->apiKey ?? '')
                ->post(($this->baseUrl ?: 'https://api.openai.com/v1').'/chat/completions', [
                    'model' => $this->modelKey,
                    'messages' => $messages,
                    'temperature' => $this->temperature,
                    'response_format' => ['type' => 'json_object'],
                ]);

            $this->assertOk($response);
        } catch (\Throwable $e) {
            $this->throwFromException($e);
        }

        return $this->decodeJson($response->json('choices.0.message.content') ?? '');
    }

    protected function providerName(): string
    {
        return 'OpenAI';
    }
}
