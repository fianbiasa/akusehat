<?php

namespace App\Services\AI\Providers;

/**
 * LM Studio's local server speaks the OpenAI wire format - reused here
 * against a local base_url instead of api.openai.com, per
 * docs/06-AI-Provider-Interface.md §3.
 */
class LMStudioProvider extends AbstractHttpProvider
{
    protected function completeChat(array $messages, array $context): array
    {
        try {
            $response = $this->http()
                ->withToken($this->apiKey ?? 'lm-studio')
                ->post(rtrim($this->baseUrl ?: 'http://localhost:1234', '/').'/v1/chat/completions', [
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
        return 'LM Studio';
    }
}
