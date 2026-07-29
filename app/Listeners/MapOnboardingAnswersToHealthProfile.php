<?php

namespace App\Listeners;

use App\Events\OnboardingCompleted;
use App\Models\KbDisease;
use App\Models\OnboardingAnswer;
use App\Models\User;
use App\Services\HealthProfileService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Populates health_profiles/lifestyle_profiles/user_diseases/user_allergies/
 * user_medications/body_measurements from the answers just submitted.
 * Matched by onboarding_questions.step, which is fixed by
 * OnboardingQuestionSeeder - the two must stay in sync.
 */
class MapOnboardingAnswersToHealthProfile
{
    private const GENDER_MAP = ['Laki-laki' => 'male', 'Perempuan' => 'female'];

    private const LOCALE_MAP = ['Bahasa Indonesia' => 'id', 'English' => 'en'];

    private const ACTIVITY_LEVEL_MAP = ['Duduk terus' => 'sedentary', 'Ringan' => 'light', 'Sedang' => 'moderate', 'Berat' => 'heavy'];

    private const FREQUENCY_MAP = ['Tidak pernah' => 'never', 'Jarang' => 'rarely', 'Sering' => 'often', 'Setiap hari' => 'daily', 'Setiap minggu' => 'daily'];

    private const SMOKING_MAP = ['Tidak pernah' => 'never', 'Sudah berhenti' => 'former', 'Kadang-kadang' => 'current', 'Rutin' => 'current'];

    private const EXERCISE_FREQUENCY_MAP = ['Tidak pernah' => 'never', '1-2 kali' => '1_2_week', '3-4 kali' => '3_4_week', '5+ kali' => '5plus_week'];

    private const MANAGED_STATUSES = ['Obat rutin', 'Kontrol dokter berkala', 'Perubahan gaya hidup saja'];

    public function __construct(private HealthProfileService $healthProfileService) {}

    public function handle(OnboardingCompleted $event): void
    {
        $user = $event->session->user;
        $answers = $event->session->answers()->with('question')->get()->keyBy(fn (OnboardingAnswer $a) => $a->question->step);

        $value = fn (int $step) => $answers->get($step)?->answer_value;

        DB::transaction(function () use ($user, $value) {
            $user->update(array_filter([
                'phone' => $value(4),
                'locale' => self::LOCALE_MAP[$value(5)] ?? null,
            ]));

            $user->healthProfile()->updateOrCreate([], array_filter([
                'gender' => self::GENDER_MAP[$value(2)] ?? null,
                'date_of_birth' => $value(3),
                'height_cm' => $value(6),
                'initial_weight_kg' => $value(7),
            ], fn ($v) => $v !== null));

            $user->lifestyleProfile()->updateOrCreate([], array_filter([
                'activity_level' => self::ACTIVITY_LEVEL_MAP[$value(11)] ?? null,
                'sleep_time' => $value(12),
                'wake_time' => $value(13),
                'work_hours_per_day' => $value(14),
                'diet_pattern' => $value(15),
                'sugary_drinks_frequency' => self::FREQUENCY_MAP[$value(16)] ?? null,
                'smoking_status' => self::SMOKING_MAP[$value(17)] ?? null,
                'alcohol_frequency' => self::FREQUENCY_MAP[$value(18)] ?? null,
                'exercise_frequency' => self::EXERCISE_FREQUENCY_MAP[$value(19)] ?? null,
            ], fn ($v) => $v !== null));

            if ($value(7) || $value(8)) {
                $measurement = $user->bodyMeasurements()->whereDate('measured_at', now())->first()
                    ?? $user->bodyMeasurements()->make(['measured_at' => now()]);

                $measurement->fill(array_filter(['weight_kg' => $value(7), 'waist_cm' => $value(8)], fn ($v) => $v !== null))->save();
            }

            $this->mapDiseases($user, $value(21), $value(23));
            $this->mapRepeatable($user->medications(), $value(25), fn ($row) => ['name' => $row['name'] ?? null, 'dosage' => $row['dosage'] ?? null]);
            $this->mapRepeatable($user->allergies(), $value(27), fn ($row) => ['allergen' => $row['allergen'] ?? null, 'notes' => $row['severity'] ?? null]);
        });

        $this->healthProfileService->recalculate($user);
    }

    private function mapDiseases(User $user, ?array $diseaseNames, ?string $managementAnswer): void
    {
        if (! $diseaseNames) {
            return;
        }

        $status = in_array($managementAnswer, self::MANAGED_STATUSES, true) ? 'managed' : 'active';

        foreach (KbDisease::whereIn('name', $diseaseNames)->get() as $disease) {
            $user->diseases()->updateOrCreate(
                ['kb_disease_id' => $disease->id],
                ['status' => $status],
            );
        }
    }

    private function mapRepeatable(HasMany $relation, ?array $rows, callable $shape): void
    {
        foreach ($rows ?? [] as $row) {
            $attributes = array_filter($shape($row), fn ($v) => filled($v));

            if ($attributes) {
                $relation->create($attributes);
            }
        }
    }
}
