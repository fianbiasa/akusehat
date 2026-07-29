<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AIProviderException;
use App\Services\AI\Contracts\AIProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Shared plumbing for every provider: all 7 interface capabilities funnel
 * into a single completeChat() a subclass implements with its own wire
 * format, since the difference between e.g. generatePlan() and
 * dailyMotivation() is entirely which prompt template built $context -
 * not how the HTTP call is shaped.
 */
abstract class AbstractHttpProvider implements AIProviderInterface
{
    public function __construct(
        protected ?string $apiKey = null,
        protected ?string $baseUrl = null,
        protected string $modelKey = '',
        protected float $temperature = 0.7,
    ) {}

    public function analyze(array $context): array
    {
        return $this->completeFromPrompt($context);
    }

    public function generatePlan(array $context): array
    {
        return $this->completeFromPrompt($context);
    }

    public function weeklyReview(array $context): array
    {
        return $this->completeFromPrompt($context);
    }

    public function dailyMotivation(array $context): array
    {
        return $this->completeFromPrompt($context);
    }

    public function mealSuggestion(array $context): array
    {
        return $this->completeFromPrompt($context);
    }

    public function workoutSuggestion(array $context): array
    {
        return $this->completeFromPrompt($context);
    }

    public function chat(array $messages, array $context = []): array
    {
        return $this->completeChat($this->normalizeMessages($messages, $context), $context);
    }

    private function completeFromPrompt(array $context): array
    {
        $messages = [['role' => 'user', 'content' => $context['prompt'] ?? '']];

        return $this->completeChat($messages, $context);
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    private function normalizeMessages(array $messages, array $context): array
    {
        if (isset($context['prompt'])) {
            return [...($messages ? [['role' => 'system', 'content' => $context['prompt']]] : []), ...$messages];
        }

        return $messages;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    abstract protected function completeChat(array $messages, array $context): array;

    protected function http(): PendingRequest
    {
        return Http::timeout(30)->connectTimeout(10);
    }

    /**
     * Extract the first {...} block and json_decode it. Providers without
     * a strict JSON mode (Groq, some Ollama models) sometimes wrap JSON in
     * prose or code fences despite instructions.
     */
    protected function decodeJson(string $raw): array
    {
        $trimmed = trim($raw);

        if (! str_starts_with($trimmed, '{') && preg_match('/\{.*\}/s', $trimmed, $matches)) {
            $trimmed = $matches[0];
        }

        $decoded = json_decode($trimmed, true);

        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw new AIProviderException('Provider response was not valid JSON.', retryable: true, invalidJson: true);
        }

        return $decoded;
    }

    protected function throwFromException(\Throwable $e): never
    {
        if ($e instanceof ConnectionException) {
            throw new AIProviderException("Connection to {$this->providerName()} timed out or failed.", retryable: true, timeout: true, previous: $e);
        }

        if ($e instanceof RequestException) {
            $status = $e->response->status();
            $retryable = $status >= 500 || $status === 429;

            throw new AIProviderException("{$this->providerName()} request failed with HTTP {$status}.", retryable: $retryable, previous: $e);
        }

        throw new AIProviderException("{$this->providerName()} request failed: {$e->getMessage()}", retryable: true, previous: $e);
    }

    protected function assertOk(Response $response): void
    {
        if ($response->failed()) {
            $response->throw();
        }
    }

    abstract protected function providerName(): string;
}
