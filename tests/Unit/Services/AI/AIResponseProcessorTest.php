<?php

namespace Tests\Unit\Services\AI;

use App\Models\Role;
use App\Models\User;
use App\Services\AI\AIProviderException;
use App\Services\AI\AIResponseProcessor;
use App\Services\AI\Contracts\AIProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIResponseProcessorTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'member')->value('id')]);
        $user->healthProfile()->create(['gender' => 'male', 'date_of_birth' => '1990-01-01', 'height_cm' => 170, 'initial_weight_kg' => 70]);
        $user->lifestyleProfile()->create(['activity_level' => 'light']);

        return $user->fresh();
    }

    private function schema(): array
    {
        return ['type' => 'object', 'required' => ['summary'], 'properties' => ['summary' => ['type' => 'string']]];
    }

    public function test_a_valid_first_response_succeeds_without_retrying()
    {
        $provider = new QueuedFakeProvider([
            fn () => ['summary' => 'ok'],
        ]);

        $result = app(AIResponseProcessor::class)->process($provider, 'analyze', ['prompt' => 'p', 'response_schema' => $this->schema()], $this->member());

        $this->assertSame('success', $result['status']);
        $this->assertSame(1, $result['attempts']);
        $this->assertSame(['summary' => 'ok'], $result['data']);
    }

    public function test_invalid_json_is_retried_with_a_corrective_prompt_and_can_then_succeed()
    {
        $provider = new QueuedFakeProvider([
            fn () => throw new AIProviderException('bad json', invalidJson: true),
            fn (array $context) => str_contains($context['prompt'], 'not valid JSON')
                ? ['summary' => 'recovered']
                : throw new AIProviderException('still bad', invalidJson: true),
        ]);

        $result = app(AIResponseProcessor::class)->process($provider, 'analyze', ['prompt' => 'p', 'response_schema' => $this->schema()], $this->member());

        $this->assertSame('success', $result['status']);
        $this->assertSame(2, $result['attempts']);
        $this->assertSame(['summary' => 'recovered'], $result['data']);
    }

    public function test_a_schema_mismatch_is_retried_with_the_validation_errors()
    {
        $provider = new QueuedFakeProvider([
            fn () => ['wrong_key' => 'oops'],
            fn (array $context) => str_contains($context['prompt'], 'did not match the required schema')
                ? ['summary' => 'fixed']
                : ['wrong_key' => 'still wrong'],
        ]);

        $result = app(AIResponseProcessor::class)->process($provider, 'analyze', ['prompt' => 'p', 'response_schema' => $this->schema()], $this->member());

        $this->assertSame('success', $result['status']);
        $this->assertSame(['summary' => 'fixed'], $result['data']);
    }

    public function test_exhausting_all_retries_falls_back_to_a_rule_engine_marked_payload()
    {
        $provider = new QueuedFakeProvider([
            fn () => ['wrong_key' => 'a'],
            fn () => ['wrong_key' => 'b'],
            fn () => ['wrong_key' => 'c'],
        ]);

        $result = app(AIResponseProcessor::class)->process($provider, 'analyze', ['prompt' => 'p', 'response_schema' => $this->schema()], $this->member());

        $this->assertSame('invalid_json', $result['status']);
        $this->assertSame(3, $result['attempts']);
        $this->assertTrue($result['data']['ai_unavailable']);
        $this->assertArrayHasKey('rule_engine_output', $result['data']);
        $this->assertArrayHasKey('calorie_target', $result['data']['rule_engine_output']);
    }

    public function test_a_transport_failure_propagates_immediately_without_consuming_retries()
    {
        $provider = new QueuedFakeProvider([
            fn () => throw new AIProviderException('network down', retryable: true, timeout: true),
        ]);

        $this->expectException(AIProviderException::class);

        app(AIResponseProcessor::class)->process($provider, 'analyze', ['prompt' => 'p', 'response_schema' => $this->schema()], $this->member());
    }

    public function test_chat_capability_passes_through_the_messages_array()
    {
        $provider = new QueuedFakeProvider([
            fn () => ['reply' => 'hi there'],
        ]);

        $result = app(AIResponseProcessor::class)->process(
            $provider,
            'chat',
            ['prompt' => 'system', 'response_schema' => ['type' => 'object', 'required' => ['reply'], 'properties' => ['reply' => ['type' => 'string']]], 'messages' => [['role' => 'user', 'content' => 'hello']]],
            $this->member(),
        );

        $this->assertSame('success', $result['status']);
        $this->assertSame([['role' => 'user', 'content' => 'hello']], $provider->receivedMessages[0]);
    }
}

/**
 * A minimal AIProviderInterface test double: each capability call pops the
 * next closure off the queue and invokes it with the call's $context,
 * letting a test assert on / react to the corrective prompt appended on
 * a retry.
 */
class QueuedFakeProvider implements AIProviderInterface
{
    private int $index = 0;

    public array $receivedMessages = [];

    /** @param array<int, callable(array): array> $responses */
    public function __construct(private array $responses) {}

    private function next(array $context): array
    {
        $handler = $this->responses[$this->index] ?? throw new \RuntimeException('No more queued responses.');
        $this->index++;

        return $handler($context);
    }

    public function analyze(array $context): array
    {
        return $this->next($context);
    }

    public function chat(array $messages, array $context = []): array
    {
        $this->receivedMessages[] = $messages;

        return $this->next($context);
    }

    public function generatePlan(array $context): array
    {
        return $this->next($context);
    }

    public function weeklyReview(array $context): array
    {
        return $this->next($context);
    }

    public function dailyMotivation(array $context): array
    {
        return $this->next($context);
    }

    public function mealSuggestion(array $context): array
    {
        return $this->next($context);
    }

    public function workoutSuggestion(array $context): array
    {
        return $this->next($context);
    }
}
