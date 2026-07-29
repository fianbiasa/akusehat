<?php

namespace Tests\Feature\Ai;

use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedMember(): User
    {
        return User::factory()->create(['onboarding_completed_at' => now()]);
    }

    public function test_the_ai_settings_page_renders()
    {
        $this->actingAs($this->onboardedMember())->get('/ai/settings')->assertOk();
    }

    public function test_a_member_can_add_a_provider_and_it_never_exposes_the_raw_api_key()
    {
        $user = $this->onboardedMember();
        $provider = AiProvider::where('slug', 'openai')->first();

        $response = $this->actingAs($user)->post('/ai/settings', [
            'provider_id' => $provider->id,
            'model_id' => $provider->models()->first()->id,
            'api_key' => 'sk-super-secret',
            'temperature' => 0.7,
            'is_default' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $setting = $user->aiSettings()->first();
        $this->assertNotNull($setting);
        $this->assertNotSame('sk-super-secret', $setting->api_key_encrypted);
        $this->assertSame('sk-super-secret', $setting->decryptedApiKey());

        $page = $this->actingAs($user)->get('/ai/settings');
        $page->assertOk();
        $page->assertDontSee('sk-super-secret');
    }

    public function test_a_member_can_add_a_local_provider_without_sending_an_api_key_field_at_all()
    {
        $user = $this->onboardedMember();
        $provider = AiProvider::where('slug', 'ollama')->first();

        $response = $this->actingAs($user)->post('/ai/settings', [
            'provider_id' => $provider->id,
            'model_id' => $provider->models()->first()->id,
            'temperature' => 0.7,
            'is_default' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_ai_settings', ['user_id' => $user->id, 'provider_id' => $provider->id]);
    }

    public function test_setting_a_new_default_unsets_the_previous_one()
    {
        $user = $this->onboardedMember();
        $provider = AiProvider::where('slug', 'openai')->first();
        $model = $provider->models()->first();

        $first = $user->aiSettings()->create(['provider_id' => $provider->id, 'model_id' => $model->id, 'is_default' => true]);
        $second = $user->aiSettings()->create(['provider_id' => $provider->id, 'model_id' => $model->id, 'is_default' => false]);

        $this->actingAs($user)->post("/ai/settings/{$second->id}/set-default")->assertSessionHasNoErrors();

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_a_member_cannot_modify_another_members_setting()
    {
        $owner = $this->onboardedMember();
        $intruder = $this->onboardedMember();
        $provider = AiProvider::where('slug', 'openai')->first();
        $setting = $owner->aiSettings()->create(['provider_id' => $provider->id, 'model_id' => $provider->models()->first()->id]);

        $this->actingAs($intruder)->delete("/ai/settings/{$setting->id}")->assertForbidden();
    }

    public function test_test_connection_reports_success_for_a_reachable_provider()
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '{"ok":true}']]]], 200)]);

        $user = $this->onboardedMember();
        $provider = AiProvider::where('slug', 'openai')->first();
        $setting = $user->aiSettings()->create(['provider_id' => $provider->id, 'model_id' => $provider->models()->first()->id, 'api_key' => 'sk-test']);

        $response = $this->actingAs($user)->postJson("/ai/settings/{$setting->id}/test");

        $response->assertOk()->assertJson(['success' => true]);
    }

    public function test_test_connection_reports_failure_for_an_unreachable_provider()
    {
        Http::fake(['api.openai.com/*' => Http::response('Unauthorized', 401)]);

        $user = $this->onboardedMember();
        $provider = AiProvider::where('slug', 'openai')->first();
        $setting = $user->aiSettings()->create(['provider_id' => $provider->id, 'model_id' => $provider->models()->first()->id, 'api_key' => 'sk-bad']);

        $response = $this->actingAs($user)->postJson("/ai/settings/{$setting->id}/test");

        $response->assertOk()->assertJson(['success' => false]);
    }
}
