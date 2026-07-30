<?php

namespace App\Services\AI;

use App\Models\AiRecommendation;
use App\Models\AiRequestLog;
use App\Models\User;
use App\Models\UserAiSetting;
use App\Services\AppSettingsService;
use App\Services\RuleEngine\RuleEngineService;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the right AIProviderInterface for a user/purpose, sends the
 * request, times it, logs it - and on transport failure, fails over to
 * the user's secondary provider before giving up to a Rule-Engine-only
 * response (FR-AI-06). Never called from a web request thread; queued
 * jobs are Phase 6's job, this is the layer they'll call into.
 */
class AIGatewayService
{
    public function __construct(
        private AIProviderFactory $providerFactory,
        private PromptBuilderService $promptBuilder,
        private AIResponseProcessor $responseProcessor,
        private RuleEngineService $ruleEngineService,
        private AppSettingsService $appSettings,
    ) {}

    public function send(User $user, string $capability, string $templateKey, array $extra = []): array
    {
        $context = $this->promptBuilder->build($templateKey, $user, $extra);
        $primary = $this->defaultSettings($user);

        if (! $primary) {
            return $this->ruleEngineOnlyFallback($user, 'no_provider_configured');
        }

        try {
            return $this->attempt($user, $primary, $capability, $context, $templateKey, $extra);
        } catch (AIProviderException $primaryError) {
            $this->logFailure($user, $primary, $templateKey, $context, $primaryError);

            $secondary = $this->secondarySettings($user, $primary);

            if (! $secondary) {
                return $this->ruleEngineOnlyFallback($user, 'provider_error');
            }

            try {
                return $this->attempt($user, $secondary, $capability, $context, $templateKey, $extra);
            } catch (AIProviderException $secondaryError) {
                $this->logFailure($user, $secondary, $templateKey, $context, $secondaryError);

                return $this->ruleEngineOnlyFallback($user, 'provider_error');
            }
        }
    }

    private function attempt(User $user, UserAiSetting $settings, string $capability, array $context, string $templateKey, array $extra): array
    {
        $provider = $this->providerFactory->make(
            $settings->provider,
            $settings->model,
            $settings->decryptedApiKey(),
            (float) $settings->temperature,
        );

        $startedAt = microtime(true);
        $result = $this->responseProcessor->process($provider, $capability, [...$context, ...$extra], $user);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        AiRequestLog::create([
            'user_id' => $user->id,
            'provider_id' => $settings->provider_id,
            'model_id' => $settings->model_id,
            'purpose' => $templateKey,
            'request_payload' => ['prompt' => $context['prompt']],
            'response_payload' => $result['data'],
            'latency_ms' => $latencyMs,
            'status' => $result['status'] === 'success' ? 'success' : 'invalid_json',
            'created_at' => now(),
        ]);

        if ($result['status'] !== 'success') {
            AiRecommendation::create([
                'user_id' => $user->id,
                'type' => 'alert',
                'content' => $result['data'],
                'rationale' => 'AI response failed schema validation after retries; Rule Engine baseline used instead.',
                'status' => 'expired',
            ]);
        }

        return $result['data'];
    }

    /**
     * Falls back to the platform-wide default (Phase 12, resolving
     * PRD §6.3's open "bring-your-own-key OR platform-provided shared
     * key" question) only when the user has configured nothing of
     * their own - a user with even one personal provider always uses
     * that, never the shared key.
     */
    private function defaultSettings(User $user): ?UserAiSetting
    {
        return $user->aiSettings()->where('is_default', true)->with(['provider', 'model'])->first()
            ?? $user->aiSettings()->with(['provider', 'model'])->first()
            ?? $this->appSettings->platformDefaultAiSetting();
    }

    private function secondarySettings(User $user, UserAiSetting $exclude): ?UserAiSetting
    {
        return $user->aiSettings()->where('id', '!=', $exclude->id)->with(['provider', 'model'])->first();
    }

    private function ruleEngineOnlyFallback(User $user, string $reason): array
    {
        return [
            'ai_unavailable' => true,
            'reason' => $reason,
            'rule_engine_output' => $this->ruleEngineService->evaluate($user),
        ];
    }

    private function logFailure(User $user, UserAiSetting $settings, string $templateKey, array $context, AIProviderException $e): void
    {
        AiRequestLog::create([
            'user_id' => $user->id,
            'provider_id' => $settings->provider_id,
            'model_id' => $settings->model_id,
            'purpose' => $templateKey,
            'request_payload' => ['prompt' => $context['prompt']],
            'status' => $e->isTimeout() ? 'timeout' : 'error',
            'error_message' => $e->getMessage(),
            'created_at' => now(),
        ]);

        Log::warning('AI provider call failed', [
            'user_id' => $user->id,
            'provider_id' => $settings->provider_id,
            'purpose' => $templateKey,
            'message' => $e->getMessage(),
        ]);
    }
}
