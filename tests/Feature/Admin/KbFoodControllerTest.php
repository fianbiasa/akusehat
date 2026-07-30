<?php

namespace Tests\Feature\Admin;

use App\Models\KbFood;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KbFoodControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
    }

    public function test_an_admin_can_view_the_food_list()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/kb/foods')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('foods', KbFood::count()));
    }

    public function test_an_admin_can_create_a_food()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/kb/foods', [
            'name' => 'Tempe Goreng',
            'name_local' => 'Tempe Goreng',
            'category' => 'protein',
            'serving_unit' => 'gram',
            'serving_size' => 100,
            'calories' => 200,
            'protein_g' => 15,
            'carbs_g' => 10,
            'fat_g' => 12,
            'tags' => ['halal', 'vegetarian'],
        ])->assertSessionHasNoErrors();

        $food = KbFood::where('name', 'Tempe Goreng')->firstOrFail();
        $this->assertSame(['halal', 'vegetarian'], $food->tags);
        $this->assertDatabaseHas('activity_logs', ['action' => 'kb_food.created']);
    }

    public function test_an_admin_can_update_a_food()
    {
        $admin = $this->admin();
        $food = KbFood::firstOrFail();

        $this->actingAs($admin)->patch("/admin/kb/foods/{$food->id}", [
            'name' => 'Nama Diperbarui',
            'category' => $food->category,
            'serving_unit' => $food->serving_unit,
            'serving_size' => $food->serving_size,
            'calories' => $food->calories,
            'protein_g' => $food->protein_g,
            'carbs_g' => $food->carbs_g,
            'fat_g' => $food->fat_g,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Nama Diperbarui', $food->fresh()->name);
        $this->assertDatabaseHas('activity_logs', ['action' => 'kb_food.updated']);
    }

    public function test_an_admin_can_delete_an_unused_food()
    {
        $admin = $this->admin();
        $food = KbFood::firstOrFail();

        $this->actingAs($admin)->delete("/admin/kb/foods/{$food->id}")->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('kb_foods', ['id' => $food->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'kb_food.deleted']);
    }

    public function test_a_non_admin_cannot_manage_the_food_list()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/kb/foods')->assertForbidden();
    }
}
