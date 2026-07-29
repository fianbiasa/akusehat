<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_roles_and_permissions()
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);

        $this->actingAs($admin)
            ->get('/admin/roles')
            ->assertOk();
    }

    public function test_admin_can_update_a_roles_permissions()
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        $coachRole = Role::where('name', 'coach')->first();
        $newPermissionIds = Permission::whereIn('name', ['member.view', 'analytics.view'])->pluck('id');

        $this->actingAs($admin)
            ->patch("/admin/roles/{$coachRole->id}/permissions", [
                'permission_ids' => $newPermissionIds->all(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            $newPermissionIds->all(),
            $coachRole->permissions()->pluck('permissions.id')->all(),
        );
    }

    public function test_non_admin_cannot_update_roles()
    {
        $member = User::factory()->create(['role_id' => Role::where('name', 'member')->value('id')]);
        $coachRole = Role::where('name', 'coach')->first();

        $this->actingAs($member)
            ->patch("/admin/roles/{$coachRole->id}/permissions", ['permission_ids' => []])
            ->assertForbidden();
    }
}
