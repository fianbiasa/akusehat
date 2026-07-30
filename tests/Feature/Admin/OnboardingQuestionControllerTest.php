<?php

namespace Tests\Feature\Admin;

use App\Models\OnboardingQuestion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingQuestionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
    }

    public function test_an_admin_can_view_the_question_bank()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/onboarding-questions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('questions', OnboardingQuestion::count()));
    }

    public function test_an_admin_can_create_a_question_appended_to_the_end()
    {
        $admin = $this->admin();
        $maxOrder = (int) OnboardingQuestion::max('order');

        $this->actingAs($admin)->post('/admin/onboarding-questions', [
            'category' => 'identity',
            'question_text' => 'Pertanyaan baru?',
            'input_type' => 'text',
            'is_required' => true,
        ])->assertSessionHasNoErrors();

        $question = OnboardingQuestion::where('question_text', 'Pertanyaan baru?')->firstOrFail();
        $this->assertSame($maxOrder + 1, $question->order);
        $this->assertSame($maxOrder + 1, $question->step);
        $this->assertDatabaseHas('activity_logs', ['action' => 'onboarding_question.created']);
    }

    public function test_an_admin_can_update_a_question()
    {
        $admin = $this->admin();
        $question = OnboardingQuestion::orderBy('order')->firstOrFail();

        $this->actingAs($admin)->patch("/admin/onboarding-questions/{$question->id}", [
            'category' => $question->category,
            'question_text' => 'Teks yang diperbarui?',
            'input_type' => $question->input_type,
            'is_required' => $question->is_required,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Teks yang diperbarui?', $question->fresh()->question_text);
        $this->assertDatabaseHas('activity_logs', ['action' => 'onboarding_question.updated']);
    }

    public function test_an_admin_can_toggle_a_questions_active_status()
    {
        $admin = $this->admin();
        $question = OnboardingQuestion::orderBy('order')->firstOrFail();
        $this->assertTrue($question->is_active);

        $this->actingAs($admin)->post("/admin/onboarding-questions/{$question->id}/toggle-active")->assertSessionHasNoErrors();

        $this->assertFalse($question->fresh()->is_active);
        $this->assertDatabaseHas('activity_logs', ['action' => 'onboarding_question.deactivated']);
    }

    public function test_a_deactivated_question_is_excluded_from_the_real_onboarding_wizard()
    {
        $question = OnboardingQuestion::orderBy('order')->firstOrFail();
        $question->update(['is_active' => false]);

        $member = User::factory()->create();
        $response = $this->actingAs($member)->getJson('/onboarding/questions');

        $ids = collect($response->json())->pluck('id');
        $this->assertNotContains($question->id, $ids);
    }

    public function test_moving_a_question_up_swaps_order_with_the_previous_one()
    {
        $admin = $this->admin();
        $questions = OnboardingQuestion::orderBy('order')->take(2)->get();
        [$first, $second] = [$questions[0], $questions[1]];

        $this->actingAs($admin)->post("/admin/onboarding-questions/{$second->id}/move-up")->assertSessionHasNoErrors();

        $this->assertSame($first->order, $second->fresh()->order);
        $this->assertSame($second->order, $first->fresh()->order);
    }

    public function test_a_non_admin_cannot_manage_the_question_bank()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/onboarding-questions')->assertForbidden();
    }
}
