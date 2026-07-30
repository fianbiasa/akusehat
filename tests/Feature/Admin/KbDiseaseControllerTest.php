<?php

namespace Tests\Feature\Admin;

use App\Models\KbDisease;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDisease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KbDiseaseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
    }

    public function test_an_admin_can_view_the_disease_list()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/kb/diseases')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('diseases', KbDisease::count()));
    }

    public function test_an_admin_can_create_a_disease_with_a_generated_slug()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/kb/diseases', [
            'name' => 'Asam Urat Tinggi',
            'category' => 'metabolic',
            'dietary_restrictions' => ['low_purine'],
        ])->assertSessionHasNoErrors();

        $disease = KbDisease::where('name', 'Asam Urat Tinggi')->firstOrFail();
        $this->assertSame('asam-urat-tinggi', $disease->slug);
        $this->assertSame(['low_purine'], $disease->dietary_restrictions);
        $this->assertDatabaseHas('activity_logs', ['action' => 'kb_disease.created']);
    }

    public function test_an_admin_can_update_a_disease()
    {
        $admin = $this->admin();
        $disease = KbDisease::firstOrFail();

        $this->actingAs($admin)->patch("/admin/kb/diseases/{$disease->id}", [
            'name' => $disease->name,
            'category' => 'updated-category',
        ])->assertSessionHasNoErrors();

        $this->assertSame('updated-category', $disease->fresh()->category);
        $this->assertDatabaseHas('activity_logs', ['action' => 'kb_disease.updated']);
    }

    public function test_an_admin_can_delete_an_unused_disease()
    {
        $admin = $this->admin();
        $disease = KbDisease::firstOrFail();

        $this->actingAs($admin)->delete("/admin/kb/diseases/{$disease->id}")->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('kb_diseases', ['id' => $disease->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'kb_disease.deleted']);
    }

    public function test_deleting_a_disease_still_recorded_on_a_users_history_fails_gracefully()
    {
        $admin = $this->admin();
        $disease = KbDisease::firstOrFail();
        $member = User::factory()->create();
        UserDisease::create(['user_id' => $member->id, 'kb_disease_id' => $disease->id, 'status' => 'active']);

        $this->actingAs($admin)->delete("/admin/kb/diseases/{$disease->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('kb_diseases', ['id' => $disease->id]);
    }

    public function test_a_non_admin_cannot_manage_the_disease_list()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/kb/diseases')->assertForbidden();
    }
}
