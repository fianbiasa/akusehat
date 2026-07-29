<?php

namespace App\Services\AI\Providers;

/**
 * OpenAI-compatible wire format, but per docs/06-AI-Provider-Interface.md
 * §3/§7 treated as having no native JSON mode - prompt-enforced JSON only,
 * relying on AbstractHttpProvider::decodeJson()'s brace-extraction
 * fallback for any stray text around the JSON body.
 */
class GroqProvider extends AbstractHttpProvider
{
    protected function completeChat(array $messages, array $context): array
    {
        try {
            $response = $this->http()
                ->withToken($this->apiKey ?? '')
                ->post(($this->baseUrl ?: 'https://api.groq.com/openai/v1').'/chat/completions', [
                    'model' => $this->modelKey,
                    'messages' => $messages,
                    'temperature' => $this->temperature,
                ]);

            $this->assertOk($response);
        } catch (\Throwable $e) {
            $this->throwFromException($e);
        }

        return $this->decodeJson($response->json('choices.0.message.content') ?? '');
    }

    protected function providerName(): string
    {
        return 'Groq';
    }
}
