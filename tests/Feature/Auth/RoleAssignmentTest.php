<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_newly_registered_user_is_assigned_the_member_role()
    {
        $this->post('/register', [
            'name' => 'New Member',
            'email' => 'new-member@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'new-member@example.com')->firstOrFail();

        $this->assertSame('member', $user->role->name);
        $this->assertTrue($user->hasPermission('own_profile.manage'));
        $this->assertFalse($user->hasPermission('users.manage'));
    }

    public function test_me_endpoint_returns_the_authenticated_user_with_role_and_permissions()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role.name', 'member')
            ->assertJsonFragment(['permissions' => ['chat.send', 'own_profile.manage', 'own_program.view', 'checkin.submit']]);
    }

    public function test_me_endpoint_requires_authentication()
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
