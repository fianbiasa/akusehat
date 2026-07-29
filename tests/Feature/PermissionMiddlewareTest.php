<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'permission:users.manage'])
            ->get('/__test/admin-only', fn () => 'ok');
    }

    public function test_a_role_with_the_required_permission_passes_the_middleware()
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);

        $this->actingAs($admin)->get('/__test/admin-only')->assertOk();
    }

    public function test_a_role_without_the_required_permission_is_forbidden()
    {
        $member = User::factory()->create(['role_id' => Role::where('name', 'member')->value('id')]);

        $this->actingAs($member)->get('/__test/admin-only')->assertForbidden();
    }

    public function test_a_guest_cannot_pass_the_middleware()
    {
        $this->get('/__test/admin-only')->assertRedirect('/login');
    }
}
