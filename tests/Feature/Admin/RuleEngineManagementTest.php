<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\RuleEngineRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuleEngineManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
    }

    public function test_admin_can_list_rules()
    {
        $this->actingAs($this->admin())->get('/admin/rule-engine/rules')->assertOk();
    }

    public function test_non_admin_cannot_list_rules()
    {
        $member = User::factory()->create(['role_id' => Role::where('name', 'member')->value('id')]);

        $this->actingAs($member)->get('/admin/rule-engine/rules')->assertForbidden();
    }

    public function test_admin_can_create_a_rule()
    {
        $this->actingAs($this->admin())->post('/admin/rule-engine/rules', [
            'category' => 'workout_level',
            'name' => 'Test rule',
            'condition' => ['bmi' => ['>=' => 25]],
            'action' => ['workout_level' => 'beginner'],
            'priority' => 100,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rule_engine_rules', ['name' => 'Test rule', 'category' => 'workout_level']);
    }

    public function test_admin_can_update_a_rule()
    {
        $rule = RuleEngineRule::create([
            'category' => 'workout_level', 'name' => 'Original', 'priority' => 100,
            'condition' => ['bmi' => ['>=' => 25]], 'action' => ['workout_level' => 'beginner'],
        ]);

        $this->actingAs($this->admin())->patch("/admin/rule-engine/rules/{$rule->id}", [
            'category' => 'workout_level',
            'name' => 'Updated',
            'condition' => ['bmi' => ['>=' => 30]],
            'action' => ['workout_level' => 'intermediate'],
            'priority' => 150,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rule_engine_rules', ['id' => $rule->id, 'name' => 'Updated', 'priority' => 150]);
    }

    public function test_admin_deleting_a_rule_deactivates_rather_than_hard_deletes()
    {
        $rule = RuleEngineRule::create([
            'category' => 'workout_level', 'name' => 'To deactivate', 'priority' => 100,
            'condition' => [], 'action' => ['workout_level' => 'beginner'],
        ]);

        $this->actingAs($this->admin())->delete("/admin/rule-engine/rules/{$rule->id}")->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rule_engine_rules', ['id' => $rule->id, 'is_active' => false]);
    }

    public function test_uji_coba_reports_whether_a_sample_profile_matches()
    {
        $rule = RuleEngineRule::create([
            'category' => 'disease_restriction', 'name' => 'Gout', 'priority' => 200,
            'condition' => ['diseases' => ['in' => ['asam-urat']]],
            'action' => ['add_restriction' => 'low_purine'],
        ]);

        $matching = $this->actingAs($this->admin())->postJson("/admin/rule-engine/rules/{$rule->id}/test", [
            'diseases' => ['asam-urat'],
        ]);
        $matching->assertOk()->assertJson(['matches' => true, 'action' => ['add_restriction' => 'low_purine']]);

        $nonMatching = $this->actingAs($this->admin())->postJson("/admin/rule-engine/rules/{$rule->id}/test", [
            'diseases' => ['hipertensi'],
        ]);
        $nonMatching->assertOk()->assertJson(['matches' => false, 'action' => null]);
    }
}
