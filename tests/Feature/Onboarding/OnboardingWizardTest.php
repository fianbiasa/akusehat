<?php

namespace Tests\Feature\Onboarding;

use App\Events\OnboardingCompleted;
use App\Jobs\GenerateProgramJob;
use App\Models\OnboardingQuestion;
use App\Models\OnboardingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OnboardingWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_onboarding_creates_an_in_progress_session()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/onboarding/sessions');

        $response->assertOk()->assertJsonPath('status', 'in_progress');
        $this->assertDatabaseHas('onboarding_sessions', ['user_id' => $user->id, 'status' => 'in_progress']);
    }

    public function test_starting_onboarding_twice_resumes_the_same_session()
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user)->postJson('/onboarding/sessions')->json('id');
        $second = $this->actingAs($user)->postJson('/onboarding/sessions')->json('id');

        $this->assertSame($first, $second);
        $this->assertSame(1, OnboardingSession::where('user_id', $user->id)->count());
    }

    public function test_submitting_an_answer_advances_the_current_step_and_is_resumable()
    {
        $user = User::factory()->create();
        $session = $user->onboardingSessions()->create(['status' => 'in_progress', 'current_step' => 1, 'started_at' => now()]);
        $question = OnboardingQuestion::where('step', 1)->firstOrFail();

        $this->actingAs($user)
            ->postJson("/onboarding/sessions/{$session->id}/answers", [
                'question_id' => $question->id,
                'value' => 'Budi Santoso',
            ])
            ->assertOk();

        $this->assertDatabaseHas('onboarding_answers', [
            'onboarding_session_id' => $session->id,
            'question_id' => $question->id,
        ]);
        $this->assertSame(2, $session->fresh()->current_step);

        // Resuming later returns the same session with the answer intact.
        $resumed = $this->actingAs($user)->postJson('/onboarding/sessions')->json();
        $this->assertSame($session->id, $resumed['id']);
        $this->assertCount(1, $resumed['answers']);
    }

    public function test_an_unrealistic_height_is_rejected_instead_of_crashing_bmi_calculation_later()
    {
        $user = User::factory()->create();
        $session = $user->onboardingSessions()->create(['status' => 'in_progress', 'current_step' => 1, 'started_at' => now()]);
        $question = OnboardingQuestion::where('step', 6)->firstOrFail(); // height_cm

        $this->actingAs($user)
            ->postJson("/onboarding/sessions/{$session->id}/answers", ['question_id' => $question->id, 'value' => 10])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('onboarding_answers', [
            'onboarding_session_id' => $session->id,
            'question_id' => $question->id,
        ]);
    }

    public function test_a_non_array_value_for_a_repeatable_question_is_rejected_instead_of_crashing_later()
    {
        $user = User::factory()->create();
        $session = $user->onboardingSessions()->create(['status' => 'in_progress', 'current_step' => 1, 'started_at' => now()]);
        $question = OnboardingQuestion::where('step', 25)->firstOrFail(); // repeatable medications list

        $this->actingAs($user)
            ->postJson("/onboarding/sessions/{$session->id}/answers", ['question_id' => $question->id, 'value' => 'Paracetamol'])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('onboarding_answers', [
            'onboarding_session_id' => $session->id,
            'question_id' => $question->id,
        ]);
    }

    public function test_completing_the_wizard_requires_every_required_question_answered()
    {
        $user = User::factory()->create();
        $session = $user->onboardingSessions()->create(['status' => 'in_progress', 'current_step' => 1, 'started_at' => now()]);

        $this->actingAs($user)
            ->postJson("/onboarding/sessions/{$session->id}/complete")
            ->assertStatus(422)
            ->assertJsonStructure(['missing_questions']);

        $this->assertNull($user->fresh()->onboarding_completed_at);
    }

    public function test_completing_the_wizard_fires_onboarding_completed_and_dispatches_program_generation()
    {
        Event::fake([OnboardingCompleted::class]);

        $user = User::factory()->create();
        $session = $user->onboardingSessions()->create(['status' => 'in_progress', 'current_step' => 1, 'started_at' => now()]);

        foreach (OnboardingQuestion::where('is_required', true)->get() as $question) {
            $session->answers()->create([
                'question_id' => $question->id,
                'answer_value' => $this->sampleAnswerFor($question),
                'answered_at' => now(),
            ]);
        }

        $this->actingAs($user)
            ->postJson("/onboarding/sessions/{$session->id}/complete")
            ->assertOk();

        $this->assertSame('completed', $session->fresh()->status);
        $this->assertNotNull($user->fresh()->onboarding_completed_at);
        Event::assertDispatched(OnboardingCompleted::class, fn ($event) => $event->session->is($session));
    }

    public function test_the_dispatched_listener_bootstraps_a_program_and_queues_generation()
    {
        Bus::fake();

        $user = User::factory()->create();
        $session = $user->onboardingSessions()->create(['status' => 'in_progress', 'current_step' => 1, 'started_at' => now()]);

        foreach (OnboardingQuestion::where('is_required', true)->get() as $question) {
            $session->answers()->create([
                'question_id' => $question->id,
                'answer_value' => $this->sampleAnswerFor($question),
                'answered_at' => now(),
            ]);
        }

        $this->actingAs($user)->postJson("/onboarding/sessions/{$session->id}/complete")->assertOk();

        $this->assertDatabaseHas('user_programs', ['user_id' => $user->id, 'status' => 'active']);
        Bus::assertDispatched(GenerateProgramJob::class, fn ($job) => $job->userProgram->user_id === $user->id);
    }

    public function test_a_user_cannot_answer_into_another_users_session()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $session = $owner->onboardingSessions()->create(['status' => 'in_progress', 'current_step' => 1, 'started_at' => now()]);
        $question = OnboardingQuestion::where('step', 1)->firstOrFail();

        $this->actingAs($intruder)
            ->postJson("/onboarding/sessions/{$session->id}/answers", ['question_id' => $question->id, 'value' => 'x'])
            ->assertForbidden();
    }

    private function sampleAnswerFor(OnboardingQuestion $question): mixed
    {
        return match ($question->input_type) {
            'multi_choice' => [$question->options[0]],
            'single_choice' => $question->options[0],
            'scale' => 3,
            'number' => 10,
            'date' => '1990-01-01',
            'time' => '07:00',
            default => 'Jawaban contoh',
        };
    }
}
