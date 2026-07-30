<?php

namespace Tests\Feature\Admin;

use App\Models\AiProvider;
use App\Models\Role;
use App\Models\User;
use App\Services\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
    }

    public function test_an_admin_can_view_the_settings_page()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('maintenanceMode.enabled', false)->where('aiDefault', null));
    }

    public function test_an_admin_can_set_the_platform_default_ai_provider()
    {
        $admin = $this->admin();
        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $model = $provider->models()->firstOrFail();

        $this->actingAs($admin)->patch('/admin/settings/ai-default', [
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'api_key' => 'sk-platform',
            'temperature' => 0.6,
        ])->assertSessionHasNoErrors();

        $this->assertNotNull(app(AppSettingsService::class)->platformDefaultAiSetting());
        $this->assertDatabaseHas('activity_logs', ['action' => 'app_setting.ai_default_updated']);
    }

    public function test_an_admin_can_toggle_maintenance_mode()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->patch('/admin/settings/maintenance-mode', [
            'enabled' => true,
            'message' => 'Perawatan terjadwal.',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(app(AppSettingsService::class)->isMaintenanceMode());
        $this->assertDatabaseHas('activity_logs', ['action' => 'app_setting.maintenance_mode_updated']);
    }

    public function test_a_non_admin_cannot_view_or_edit_platform_settings()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/settings')->assertForbidden();
        $this->actingAs($member)->patch('/admin/settings/maintenance-mode', ['enabled' => true])->assertForbidden();
    }
}
