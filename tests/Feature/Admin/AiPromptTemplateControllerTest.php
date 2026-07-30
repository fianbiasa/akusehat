<?php

namespace Tests\Feature\Admin;

use App\Models\AiPromptTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPromptTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
    }

    public function test_an_admin_can_view_the_prompt_template_list()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/ai/prompt-templates')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('templates', AiPromptTemplate::count()));
    }

    public function test_saving_a_template_bumps_its_version()
    {
        $admin = $this->admin();
        $template = AiPromptTemplate::where('key', 'meal_plan')->firstOrFail();
        $originalVersion = $template->version;

        $this->actingAs($admin)->patch("/admin/ai/prompt-templates/{$template->id}", [
            'purpose' => $template->purpose,
            'template' => 'Template baru {{ user_profile }}',
            'variables' => ['user_profile'],
            'response_schema' => ['type' => 'object'],
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $fresh = $template->fresh();
        $this->assertSame($originalVersion + 1, $fresh->version);
        $this->assertSame('Template baru {{ user_profile }}', $fresh->template);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ai_prompt_template.updated']);
    }

    public function test_the_template_key_cannot_be_changed_via_update()
    {
        $admin = $this->admin();
        $template = AiPromptTemplate::where('key', 'meal_plan')->firstOrFail();

        $this->actingAs($admin)->patch("/admin/ai/prompt-templates/{$template->id}", [
            'key' => 'hijacked_key',
            'purpose' => $template->purpose,
            'template' => $template->template,
            'variables' => $template->variables,
            'response_schema' => $template->response_schema,
        ])->assertSessionHasNoErrors();

        $this->assertSame('meal_plan', $template->fresh()->key);
    }

    public function test_a_non_admin_cannot_manage_prompt_templates()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/ai/prompt-templates')->assertForbidden();
    }
}
