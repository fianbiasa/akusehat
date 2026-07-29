<?php

namespace Tests\Feature\Admin;

use App\Models\AiProvider;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiProviderManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
    }

    public function test_admin_can_list_providers_with_their_models()
    {
        $this->actingAs($this->admin())->get('/admin/ai/providers')->assertOk();
    }

    public function test_non_admin_cannot_list_providers()
    {
        $member = User::factory()->create(['role_id' => Role::where('name', 'member')->value('id')]);

        $this->actingAs($member)->get('/admin/ai/providers')->assertForbidden();
    }

    public function test_admin_can_toggle_a_providers_active_state()
    {
        $provider = AiProvider::where('slug', 'ollama')->first();

        $this->actingAs($this->admin())->patch("/admin/ai/providers/{$provider->id}", [
            'name' => $provider->name,
            'type' => $provider->type,
            'is_active' => false,
        ])->assertSessionHasNoErrors();

        $this->assertFalse($provider->fresh()->is_active);
    }

    public function test_admin_can_add_a_model_to_a_provider()
    {
        $provider = AiProvider::where('slug', 'openai')->first();

        $this->actingAs($this->admin())->post("/admin/ai/providers/{$provider->id}/models", [
            'name' => 'GPT-5.5 Mini',
            'model_key' => 'gpt-5.5-mini',
            'input_cost_per_1k' => 0.0001,
            'output_cost_per_1k' => 0.0004,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ai_models', ['provider_id' => $provider->id, 'model_key' => 'gpt-5.5-mini']);
    }
}
