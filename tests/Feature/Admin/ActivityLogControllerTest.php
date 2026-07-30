<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_view_the_activity_log()
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        ActivityLog::create(['user_id' => $admin->id, 'action' => 'user.created', 'created_at' => now()]);

        $this->actingAs($admin)->get('/admin/activity-log')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('logs.data', 1));
    }

    public function test_the_activity_log_can_be_filtered_by_action()
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        ActivityLog::create(['user_id' => $admin->id, 'action' => 'user.created', 'created_at' => now()]);
        ActivityLog::create(['user_id' => $admin->id, 'action' => 'role.permissions_updated', 'created_at' => now()]);

        $this->actingAs($admin)->get('/admin/activity-log?action=user.')
            ->assertInertia(fn ($page) => $page->has('logs.data', 1)->where('logs.data.0.action', 'user.created'));
    }

    public function test_a_member_cannot_view_the_activity_log()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/activity-log')->assertForbidden();
    }

    public function test_creating_a_user_records_an_activity_log_entry()
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        $roleId = Role::where('name', 'member')->value('id');

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Member',
            'email' => 'new-member@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role_id' => $roleId,
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'user.created',
        ]);
    }

    public function test_updating_role_permissions_records_an_activity_log_entry()
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        $coachRole = Role::where('name', 'coach')->first();

        $this->actingAs($admin)->patch("/admin/roles/{$coachRole->id}/permissions", [
            'permission_ids' => [],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'role.permissions_updated',
            'subject_id' => $coachRole->id,
        ]);
    }
}
