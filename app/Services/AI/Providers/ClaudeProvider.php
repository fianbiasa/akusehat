<?php

namespace App\Services\AI\Providers;

/**
 * Anthropic doesn't offer a strict "json_object" response_format like
 * OpenAI/Gemini - it relies on prompt-enforced JSON (the fixed instruction
 * block every template ends with) validated post-hoc, per
 * docs/06-AI-Provider-Interface.md §3.
 */
class ClaudeProvider extends AbstractHttpProvider
{
    protected function completeChat(array $messages, array $context): array
    {
        try {
            $response = $this->http()
                ->withHeaders([
                    'x-api-key' => $this->apiKey ?? '',
                    'anthropic-version' => '2023-06-01',
                ])
                ->post(($this->baseUrl ?: 'https://api.anthropic.com/v1').'/messages', [
                    'model' => $this->modelKey,
                    'max_tokens' => 4096,
                    // Newer Claude models (e.g. claude-sonnet-5) reject a
                    // custom `temperature` outright: "temperature is
                    // deprecated for this model" (HTTP 400). Sampling is
                    // left at the model's default instead of exposing a
                    // control the API no longer honors.
                    'messages' => $this->toAnthropicMessages($messages),
                ]);

            $this->assertOk($response);
        } catch (\Throwable $e) {
            $this->throwFromException($e);
        }

        return $this->decodeJson($response->json('content.0.text') ?? '');
    }

    /**
     * Anthropic has no "system" role in the messages array - system
     * content is folded into the first user turn instead.
     */
    private function toAnthropicMessages(array $messages): array
    {
        $systemParts = [];
        $rest = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemParts[] = $message['content'];
            } else {
                $rest[] = $message;
            }
        }

        if ($systemParts && $rest) {
            $rest[0]['content'] = implode("\n\n", $systemParts)."\n\n".$rest[0]['content'];
        } elseif ($systemParts) {
            $rest[] = ['role' => 'user', 'content' => implode("\n\n", $systemParts)];
        }

        return $rest;
    }

    protected function providerName(): string
    {
        return 'Claude';
    }
}
