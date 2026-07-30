<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
    }

    public function test_admin_can_list_users()
    {
        $this->actingAs($this->admin())
            ->get('/admin/users')
            ->assertOk();
    }

    public function test_non_admin_cannot_list_users()
    {
        $member = User::factory()->create(['role_id' => Role::where('name', 'member')->value('id')]);

        $this->actingAs($member)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_admin_can_create_a_user()
    {
        $memberRoleId = Role::where('name', 'member')->value('id');

        $this->actingAs($this->admin())
            ->post('/admin/users', [
                'name' => 'New Coach',
                'email' => 'coach@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role_id' => $memberRoleId,
                'status' => 'active',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'coach@example.com', 'role_id' => $memberRoleId]);
        $this->assertNotNull(User::where('email', 'coach@example.com')->value('email_verified_at'));
    }

    public function test_admin_can_update_a_users_role_and_status()
    {
        $user = User::factory()->create();
        $coachRoleId = Role::where('name', 'coach')->value('id');

        $this->actingAs($this->admin())
            ->patch("/admin/users/{$user->id}", [
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $coachRoleId,
                'status' => 'suspended',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role_id' => $coachRoleId, 'status' => 'suspended']);
    }

    public function test_admin_can_soft_delete_a_user()
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin())
            ->delete("/admin/users/{$user->id}")
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->trashed());
    }

    public function test_a_soft_deleted_user_no_longer_appears_in_the_users_list()
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Soon Deleted']);
        $this->actingAs($admin)->delete("/admin/users/{$user->id}");

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertInertia(fn ($page) => $page->where(
            'users.data',
            fn ($users) => collect($users)->doesntContain(fn ($u) => $u['name'] === 'Soon Deleted'),
        ));
    }

    public function test_admin_cannot_delete_their_own_account_from_this_screen()
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete("/admin/users/{$admin->id}")
            ->assertStatus(422);

        $this->assertFalse($admin->fresh()->trashed());
    }
}
