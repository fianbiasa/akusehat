<?php

namespace App\Services\AI;

use App\Models\User;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\RuleEngine\RuleEngineService;
use InvalidArgumentException;

/**
 * docs/06-AI-Provider-Interface.md §5: decode -> validate against
 * response_schema -> retry with a corrective prompt (<=2 retries) ->
 * fall back to a Rule-Engine-only payload if still failing. Provider
 * adapters already decode JSON internally and throw AIProviderException
 * on failure (see AbstractHttpProvider::decodeJson()) - this class is
 * what decides whether that's worth retrying and validates the *shape*
 * of whatever did decode.
 */
class AIResponseProcessor
{
    private const MAX_ATTEMPTS = 3; // 1 initial call + 2 corrective retries

    private const CAPABILITIES = [
        'analyze', 'chat', 'generatePlan', 'weeklyReview', 'dailyMotivation', 'mealSuggestion', 'workoutSuggestion',
    ];

    public function __construct(
        private JsonSchemaValidator $validator,
        private RuleEngineService $ruleEngineService,
    ) {}

    /**
     * @return array{data: array, status: string, attempts: int}
     */
    public function process(AIProviderInterface $provider, string $capability, array $promptContext, User $user): array
    {
        if (! in_array($capability, self::CAPABILITIES, true)) {
            throw new InvalidArgumentException("Unknown AI capability: {$capability}");
        }

        $prompt = $promptContext['prompt'] ?? '';
        $schema = $promptContext['response_schema'] ?? [];
        $lastErrors = [];

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $callContext = [...$promptContext, 'prompt' => $prompt];

            try {
                $result = $capability === 'chat'
                    ? $provider->chat($promptContext['messages'] ?? [], $callContext)
                    : $provider->{$capability}($callContext);
            } catch (AIProviderException $e) {
                if ($e->isInvalidJson() && $attempt < self::MAX_ATTEMPTS) {
                    $prompt .= "\n\nYour previous response was not valid JSON. Return ONLY valid JSON matching the schema.";

                    continue;
                }

                throw $e; // transport failure, or JSON retries exhausted - caller handles failover/fallback
            }

            $errors = $this->validator->validate($result, $schema);

            if ($errors === []) {
                return ['data' => $result, 'status' => 'success', 'attempts' => $attempt];
            }

            $lastErrors = $errors;

            if ($attempt < self::MAX_ATTEMPTS) {
                $prompt .= "\n\nYour previous response did not match the required schema (errors: "
                    .implode('; ', $errors).'). Return ONLY valid JSON matching the schema.';
            }
        }

        return [
            'data' => $this->fallback($user, $lastErrors),
            'status' => 'invalid_json',
            'attempts' => self::MAX_ATTEMPTS,
        ];
    }

    /**
     * Not a capability-shaped response - a clearly-marked degraded
     * payload the caller (AIGatewayService, eventually
     * ProgramGenerationService in Phase 6) can detect and build a real
     * Rule-Engine-only plan from.
     */
    private function fallback(User $user, array $errors): array
    {
        return [
            'ai_unavailable' => true,
            'reason' => 'AI response failed schema validation after '.self::MAX_ATTEMPTS.' attempts.',
            'schema_errors' => $errors,
            'rule_engine_output' => $this->ruleEngineService->evaluate($user),
        ];
    }
}
