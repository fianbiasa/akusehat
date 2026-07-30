<?php

namespace App\Services;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AppSetting;
use App\Models\UserAiSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * Typed access over the generic `app_settings` key-value store
 * (docs/03-Database-Dictionary.md Module 11) - callers never touch
 * raw keys/JSON shapes directly, keeping those an implementation
 * detail of this service.
 */
class AppSettingsService
{
    public function get(string $key, mixed $default = null): mixed
    {
        return AppSetting::where('key', $key)->first()?->value ?? $default;
    }

    public function set(string $key, array $value, ?string $description = null): AppSetting
    {
        return AppSetting::updateOrCreate(['key' => $key], ['value' => $value, 'description' => $description]);
    }

    public function isMaintenanceMode(): bool
    {
        return (bool) ($this->get('maintenance_mode')['enabled'] ?? false);
    }

    public function maintenanceMessage(): ?string
    {
        return $this->get('maintenance_mode')['message'] ?? null;
    }

    public function setMaintenanceMode(bool $enabled, ?string $message = null): void
    {
        $this->set('maintenance_mode', ['enabled' => $enabled, 'message' => $message], 'Platform-wide maintenance mode toggle (Admin bypass, login always reachable)');
    }

    /**
     * Builds an unsaved UserAiSetting-shaped object from the stored
     * config so AIGatewayService can treat it identically to a real
     * per-user setting (FR-AI: "platform provides a default shared
     * provider/key" per PRD §6.3) - never persisted as a real
     * user_ai_settings row since it belongs to no single user.
     */
    public function platformDefaultAiSetting(): ?UserAiSetting
    {
        $config = $this->get('ai.platform_default');

        if (! $config || empty($config['provider_id']) || empty($config['model_id']) || empty($config['api_key_encrypted'])) {
            return null;
        }

        $provider = AiProvider::find($config['provider_id']);
        $model = AiModel::find($config['model_id']);

        if (! $provider || ! $model) {
            return null;
        }

        $setting = new UserAiSetting([
            'provider_id' => $config['provider_id'],
            'model_id' => $config['model_id'],
            'temperature' => $config['temperature'] ?? 0.7,
        ]);
        $setting->api_key_encrypted = $config['api_key_encrypted'];
        $setting->setRelation('provider', $provider);
        $setting->setRelation('model', $model);

        return $setting;
    }

    public function setPlatformDefaultAiSetting(int $providerId, int $modelId, ?string $apiKey, float $temperature = 0.7): void
    {
        $existing = $this->get('ai.platform_default', []);

        $this->set('ai.platform_default', [
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'temperature' => $temperature,
            'api_key_encrypted' => $apiKey ? Crypt::encryptString($apiKey) : ($existing['api_key_encrypted'] ?? null),
        ], 'Platform default AI provider/model used when a Member has no personal AI provider configured');
    }
}
