<?php

namespace Tests\Unit\Services;

use App\Models\AiProvider;
use App\Services\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AppSettingsService
    {
        return app(AppSettingsService::class);
    }

    public function test_get_returns_the_default_when_a_key_does_not_exist()
    {
        $this->assertSame('fallback', $this->service()->get('missing.key', 'fallback'));
    }

    public function test_set_then_get_round_trips_the_value()
    {
        $this->service()->set('some.key', ['foo' => 'bar'], 'A test setting');

        $this->assertSame(['foo' => 'bar'], $this->service()->get('some.key'));
    }

    public function test_set_updates_an_existing_key_rather_than_duplicating_it()
    {
        $this->service()->set('some.key', ['n' => 1]);
        $this->service()->set('some.key', ['n' => 2]);

        $this->assertSame(['n' => 2], $this->service()->get('some.key'));
        $this->assertDatabaseCount('app_settings', 1);
    }

    public function test_maintenance_mode_defaults_to_disabled()
    {
        $this->assertFalse($this->service()->isMaintenanceMode());
        $this->assertNull($this->service()->maintenanceMessage());
    }

    public function test_maintenance_mode_can_be_toggled_with_a_message()
    {
        $this->service()->setMaintenanceMode(true, 'Sedang upgrade server.');

        $this->assertTrue($this->service()->isMaintenanceMode());
        $this->assertSame('Sedang upgrade server.', $this->service()->maintenanceMessage());
    }

    public function test_platform_default_ai_setting_is_null_when_unconfigured()
    {
        $this->assertNull($this->service()->platformDefaultAiSetting());
    }

    public function test_platform_default_ai_setting_builds_a_usable_user_ai_setting_with_a_decryptable_key()
    {
        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $model = $provider->models()->firstOrFail();

        $this->service()->setPlatformDefaultAiSetting($provider->id, $model->id, 'sk-platform-key', 0.5);

        $setting = $this->service()->platformDefaultAiSetting();

        $this->assertNotNull($setting);
        $this->assertSame($provider->id, $setting->provider_id);
        $this->assertSame($model->id, $setting->model_id);
        $this->assertSame(0.5, (float) $setting->temperature);
        $this->assertSame('sk-platform-key', $setting->decryptedApiKey());
        $this->assertSame($provider->name, $setting->provider->name);
    }

    public function test_updating_the_platform_default_without_a_new_api_key_keeps_the_existing_encrypted_key()
    {
        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $model = $provider->models()->firstOrFail();

        $this->service()->setPlatformDefaultAiSetting($provider->id, $model->id, 'sk-original', 0.7);
        $this->service()->setPlatformDefaultAiSetting($provider->id, $model->id, null, 1.0);

        $setting = $this->service()->platformDefaultAiSetting();

        $this->assertSame('sk-original', $setting->decryptedApiKey());
        $this->assertSame(1.0, (float) $setting->temperature);
    }

    public function test_platform_default_ai_setting_is_null_when_no_api_key_was_ever_set()
    {
        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $model = $provider->models()->firstOrFail();

        $this->service()->setPlatformDefaultAiSetting($provider->id, $model->id, null, 0.7);

        $this->assertNull($this->service()->platformDefaultAiSetting());
    }
}
