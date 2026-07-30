<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureNotInMaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_pass_through_normally_when_maintenance_mode_is_off()
    {
        $this->get('/login')->assertOk();
    }

    public function test_a_guest_gets_a_503_during_maintenance_mode()
    {
        config(['app.debug' => false]);
        app(AppSettingsService::class)->setMaintenanceMode(true, 'Sedang perawatan.');

        $response = $this->get('/');

        $response->assertStatus(503);
        $response->assertSee('Sedang perawatan.');
    }

    public function test_the_login_page_stays_reachable_during_maintenance_mode()
    {
        app(AppSettingsService::class)->setMaintenanceMode(true);

        $this->get('/login')->assertOk();
    }

    public function test_a_member_gets_a_503_during_maintenance_mode()
    {
        app(AppSettingsService::class)->setMaintenanceMode(true);
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $this->actingAs($user)->get('/dashboard')->assertStatus(503);
    }

    public function test_an_admin_passes_through_during_maintenance_mode()
    {
        app(AppSettingsService::class)->setMaintenanceMode(true);
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);

        $this->actingAs($admin)->get('/admin/settings')->assertOk();
    }
}
