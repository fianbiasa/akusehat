<?php

namespace App\Services\AI\Providers;

class OllamaProvider extends AbstractHttpProvider
{
    protected function completeChat(array $messages, array $context): array
    {
        try {
            $response = $this->http()
                ->post(rtrim($this->baseUrl ?: 'http://localhost:11434', '/').'/api/chat', [
                    'model' => $this->modelKey,
                    'messages' => $messages,
                    'stream' => false,
                    'format' => 'json',
                    'options' => ['temperature' => $this->temperature],
                ]);

            $this->assertOk($response);
        } catch (\Throwable $e) {
            $this->throwFromException($e);
        }

        return $this->decodeJson($response->json('message.content') ?? '');
    }

    protected function providerName(): string
    {
        return 'Ollama';
    }
}
