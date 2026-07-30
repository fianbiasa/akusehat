<?php

namespace Tests\Feature\Admin;

use App\Models\KbExercise;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KbExerciseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
    }

    public function test_an_admin_can_view_the_exercise_list()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/kb/exercises')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('exercises', KbExercise::count()));
    }

    public function test_an_admin_can_create_an_exercise()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/kb/exercises', [
            'name' => 'Jalan Cepat',
            'category' => 'cardio',
            'difficulty' => 'beginner',
            'met_value' => 4.5,
            'contraindications' => ['jantung'],
        ])->assertSessionHasNoErrors();

        $exercise = KbExercise::where('name', 'Jalan Cepat')->firstOrFail();
        $this->assertSame(['jantung'], $exercise->contraindications);
        $this->assertDatabaseHas('activity_logs', ['action' => 'kb_exercise.created']);
    }

    public function test_an_admin_can_update_an_exercise()
    {
        $admin = $this->admin();
        $exercise = KbExercise::firstOrFail();

        $this->actingAs($admin)->patch("/admin/kb/exercises/{$exercise->id}", [
            'name' => 'Nama Diperbarui',
            'difficulty' => $exercise->difficulty,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Nama Diperbarui', $exercise->fresh()->name);
        $this->assertDatabaseHas('activity_logs', ['action' => 'kb_exercise.updated']);
    }

    public function test_an_admin_can_delete_an_exercise()
    {
        $admin = $this->admin();
        $exercise = KbExercise::firstOrFail();

        $this->actingAs($admin)->delete("/admin/kb/exercises/{$exercise->id}")->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('kb_exercises', ['id' => $exercise->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'kb_exercise.deleted']);
    }

    public function test_a_non_admin_cannot_manage_the_exercise_list()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/kb/exercises')->assertForbidden();
    }
}
