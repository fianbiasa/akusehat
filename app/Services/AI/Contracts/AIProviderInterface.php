<?php

namespace App\Services\AI\Contracts;

/**
 * Every supported provider implements this same contract
 * (docs/06-AI-Provider-Interface.md §2). AIGatewayService never calls a
 * provider SDK directly - it resolves an implementation of this interface
 * at runtime via ai_providers.driver_class, so switching providers is a
 * configuration change, never a code change.
 *
 * Every method:
 * 1. Receives a $context array already assembled by PromptBuilderService
 *    (never raw user text) - at minimum $context['prompt'] (the fully
 *    resolved prompt string) and $context['response_schema'].
 * 2. Translates $context into the provider's native request format,
 *    requesting native JSON mode where supported (OpenAI, Gemini) and
 *    falling back to strict prompt-enforced JSON + parsing otherwise.
 * 3. Returns a plain PHP array decoded from the provider's JSON response -
 *    never the provider SDK's response object.
 * 4. Throws AIProviderException on failure.
 */
interface AIProviderInterface
{
    /**
     * General-purpose structured analysis (e.g. Health Score explanation,
     * onboarding baseline narrative).
     */
    public function analyze(array $context): array;

    /**
     * Free-form conversational turn. Still returns structured JSON
     * (message + optional suggested actions), never raw markdown/HTML.
     */
    public function chat(array $messages, array $context = []): array;

    /** Full program generation: meal plan, workout plan, checklist, targets. */
    public function generatePlan(array $context): array;

    /** Weekly progress summary + next-week adjustments. */
    public function weeklyReview(array $context): array;

    /** Short motivational message, personalized to recent progress. */
    public function dailyMotivation(array $context): array;

    /** Alternative meal suggestion respecting the same macro/KB constraints. */
    public function mealSuggestion(array $context): array;

    /** Alternative workout suggestion respecting the same constraints. */
    public function workoutSuggestion(array $context): array;
}
