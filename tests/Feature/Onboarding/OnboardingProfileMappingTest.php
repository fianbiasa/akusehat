<?php

namespace Tests\Feature\Onboarding;

use App\Models\OnboardingQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingProfileMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_the_wizard_populates_health_and_lifestyle_profiles()
    {
        $user = $this->completeWizardWithRealisticAnswers();

        $health = $user->fresh()->healthProfile;
        $this->assertSame('male', $health->gender);
        $this->assertSame('1990-05-15', $health->date_of_birth->toDateString());
        $this->assertEqualsWithDelta(175.0, (float) $health->height_cm, 0.01);
        $this->assertEqualsWithDelta(80.0, (float) $health->initial_weight_kg, 0.01);
        $this->assertNotNull($health->bmi, 'BMI should be auto-computed once height/weight/dob/gender are known');

        $lifestyle = $user->lifestyleProfile;
        $this->assertSame('moderate', $lifestyle->activity_level);
        $this->assertSame('current', $lifestyle->smoking_status);
        $this->assertSame('rarely', $lifestyle->alcohol_frequency);
    }

    public function test_completing_the_wizard_logs_todays_body_measurement()
    {
        $user = $this->completeWizardWithRealisticAnswers();

        $measurement = $user->bodyMeasurements()->whereDate('measured_at', now())->first();

        $this->assertNotNull($measurement);
        $this->assertEqualsWithDelta(80.0, (float) $measurement->weight_kg, 0.01);
        $this->assertEqualsWithDelta(90.0, (float) $measurement->waist_cm, 0.01);
    }

    public function test_completing_the_wizard_creates_user_diseases_from_the_kb()
    {
        $user = $this->completeWizardWithRealisticAnswers();

        $this->assertSame(['Hipertensi'], $user->diseases()->with('disease')->get()->pluck('disease.name')->all());
    }

    public function test_completing_the_wizard_creates_medications_and_allergies_from_repeatable_rows()
    {
        $user = $this->completeWizardWithRealisticAnswers();

        $this->assertDatabaseHas('user_medications', ['user_id' => $user->id, 'name' => 'Amlodipine', 'dosage' => '5mg']);
        $this->assertDatabaseHas('user_allergies', ['user_id' => $user->id, 'allergen' => 'Kacang']);
    }

    private function completeWizardWithRealisticAnswers(): User
    {
        $user = User::factory()->create();
        $session = $user->onboardingSessions()->create(['status' => 'in_progress', 'current_step' => 1, 'started_at' => now()]);

        $answersByStep = [
            1 => 'Budi Santoso',
            2 => 'Laki-laki',
            3 => '1990-05-15',
            4 => '08123456789',
            5 => 'Bahasa Indonesia',
            6 => 175,
            7 => 80,
            8 => 90,
            9 => 75,
            10 => 12,
            11 => 'Sedang',
            12 => '22:00',
            13 => '05:30',
            14 => 8,
            15 => 'Teratur 3x sehari',
            16 => 'Jarang',
            17 => 'Kadang-kadang',
            18 => 'Jarang',
            19 => '1-2 kali',
            20 => 'Pagi',
            21 => ['Hipertensi'],
            23 => 'Obat rutin',
            24 => 'Ya',
            25 => [['name' => 'Amlodipine', 'dosage' => '5mg']],
            26 => 'Ya',
            27 => [['allergen' => 'Kacang', 'severity' => 'Sedang']],
        ];

        $questions = OnboardingQuestion::where('is_required', true)
            ->orWhereIn('step', array_keys($answersByStep))
            ->orderBy('step')
            ->get();

        foreach ($questions as $question) {
            $value = $answersByStep[$question->step] ?? $this->fallbackAnswer($question);

            $this->actingAs($user)->postJson("/onboarding/sessions/{$session->id}/answers", [
                'question_id' => $question->id,
                'value' => $value,
            ])->assertOk();
        }

        $this->actingAs($user)->postJson("/onboarding/sessions/{$session->id}/complete")->assertOk();

        return $user;
    }

    private function fallbackAnswer(OnboardingQuestion $question): mixed
    {
        return match ($question->input_type) {
            'multi_choice' => [$question->options[0]],
            'single_choice' => $question->options[0],
            'scale' => $question->options['min'],
            'number' => 10,
            'date' => '1990-01-01',
            'time' => '07:00',
            default => 'Jawaban contoh',
        };
    }
}
