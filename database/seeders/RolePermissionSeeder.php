<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Baseline roles + permissions per PRD §10 (docs/01-PRD.md).
     * Later phases add module-specific permissions as those modules are built.
     */
    public function run(): void
    {
        $roles = [
            'admin' => ['label' => 'Admin', 'description' => 'Platform operator'],
            'coach' => ['label' => 'Coach', 'description' => 'Health professional managing assigned Members'],
            'member' => ['label' => 'Member', 'description' => 'End user'],
        ];

        foreach ($roles as $name => $attributes) {
            Role::updateOrCreate(['name' => $name], $attributes);
        }

        $permissions = [
            'users.manage' => 'users',
            'roles.manage' => 'users',
            'ai.manage' => 'ai',
            'rule_engine.manage' => 'rule_engine',
            'knowledge_base.manage' => 'knowledge_base',
            'analytics.view' => 'analytics',
            'member.view' => 'coach',
            'program.review' => 'program',
            'note.manage' => 'coach',
            'chat.send' => 'chat',
            'own_profile.manage' => 'profile',
            'own_program.view' => 'program',
            'checkin.submit' => 'program',
            'coach_members.manage' => 'coach',
            'subscriptions.manage' => 'subscriptions',
            'app_settings.manage' => 'app_settings',
        ];

        foreach ($permissions as $name => $module) {
            Permission::updateOrCreate(['name' => $name], ['module' => $module]);
        }

        $grants = [
            'admin' => array_keys($permissions), // full access across every module
            'coach' => ['member.view', 'program.review', 'note.manage', 'chat.send'],
            'member' => ['own_profile.manage', 'own_program.view', 'checkin.submit', 'chat.send'],
        ];

        foreach ($grants as $roleName => $permissionNames) {
            Role::where('name', $roleName)->first()
                ->permissions()
                ->sync(Permission::whereIn('name', $permissionNames)->pluck('id'));
        }
    }
}
