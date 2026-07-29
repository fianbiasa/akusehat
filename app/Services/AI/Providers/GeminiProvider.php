<?php

namespace App\Services\AI\Providers;

class GeminiProvider extends AbstractHttpProvider
{
    protected function completeChat(array $messages, array $context): array
    {
        $baseUrl = $this->baseUrl ?: 'https://generativelanguage.googleapis.com/v1beta';

        try {
            $response = $this->http()
                ->withQueryParameters(['key' => $this->apiKey ?? ''])
                ->post("{$baseUrl}/models/{$this->modelKey}:generateContent", [
                    'contents' => $this->toGeminiContents($messages),
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature' => $this->temperature,
                    ],
                ]);

            $this->assertOk($response);
        } catch (\Throwable $e) {
            $this->throwFromException($e);
        }

        return $this->decodeJson($response->json('candidates.0.content.parts.0.text') ?? '');
    }

    /**
     * Gemini uses "user"/"model" roles (not "assistant") and has no
     * "system" role - fold any system content into the first user turn.
     */
    private function toGeminiContents(array $messages): array
    {
        $contents = [];

        foreach ($messages as $message) {
            $role = match ($message['role']) {
                'assistant' => 'model',
                default => 'user',
            };

            $contents[] = ['role' => $role, 'parts' => [['text' => $message['content']]]];
        }

        return $contents;
    }

    protected function providerName(): string
    {
        return 'Gemini';
    }
}
