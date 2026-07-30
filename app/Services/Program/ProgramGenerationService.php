<?php

namespace App\Services\Program;

use App\Models\KbExercise;
use App\Models\KbFood;
use App\Models\OnboardingSession;
use App\Models\Program;
use App\Models\ProgramGoal;
use App\Models\Reminder;
use App\Models\User;
use App\Models\UserProgram;
use App\Models\WeeklyPlan;
use App\Services\Admin\ActivityLogger;
use App\Services\AI\AIGatewayService;
use App\Services\RuleEngine\RuleEngineService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates Goal -> RuleEngine -> AI generatePlan -> persist
 * (FR-PROG-03). Generation is per-day, not a 90-day batch upfront - an
 * adaptive coaching plan that gets reviewed/adjusted weekly
 * (GenerateWeeklyReviewJob) has no sound reason to pre-generate 90 days
 * of content that will likely be superseded, and doing so would mean
 * 180+ AI calls per program just to bootstrap it. Regenerating "the
 * remaining plan" (per 05-API-Specification.md §6) is interpreted as
 * regenerating the current day going forward, one day at a time, on
 * demand - see docs/11-Development-Checklist.md Phase 6 notes.
 */
class ProgramGenerationService
{
    public function __construct(
        private RuleEngineService $ruleEngineService,
        private AIGatewayService $aiGateway,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * Creates the UserProgram + ProgramGoal + Reminders from the answers
     * just submitted at onboarding (target weight/timeframe -> goal,
     * reminder-time answers -> reminders). Does NOT generate today's plan
     * itself - callers dispatch GenerateProgramJob for that, keeping this
     * fast/synchronous-safe.
     */
    public function bootstrap(OnboardingSession $session): UserProgram
    {
        $user = $session->user;
        $answers = $session->answers()->with('question')->get()->keyBy(fn ($a) => $a->question->step);
        $value = fn (int $step) => $answers->get($step)?->answer_value;

        $program = Program::where('slug', 'diet-90-hari')->where('is_active', true)->firstOrFail();
        $startDate = Carbon::today();

        return DB::transaction(function () use ($user, $program, $startDate, $value) {
            $userProgram = $user->programs()->create([
                'program_id' => $program->id,
                'status' => 'active',
                'start_date' => $startDate,
                'end_date' => $startDate->copy()->addDays($program->default_duration_days - 1),
                'created_by' => 'ai',
            ]);

            $this->createGoal($user, $userProgram, $startDate, $value(9), $value(10), $value(54));
            $this->createReminders($user, $value(45), $value(46), $value(47), $value(48));

            $this->activityLogger->log('program.created', $userProgram, ['program_id' => $program->id]);

            return $userProgram;
        });
    }

    private function createGoal(User $user, UserProgram $userProgram, Carbon $startDate, mixed $targetWeightKg, mixed $weeksToTarget, mixed $notes): void
    {
        $currentWeightKg = $user->healthProfile?->initial_weight_kg;
        $goalType = 'maintenance';

        if ($targetWeightKg !== null && $currentWeightKg !== null) {
            $goalType = match (true) {
                (float) $targetWeightKg < (float) $currentWeightKg => 'weight_loss',
                (float) $targetWeightKg > (float) $currentWeightKg => 'weight_gain',
                default => 'maintenance',
            };
        }

        ProgramGoal::create([
            'user_program_id' => $userProgram->id,
            'goal_type' => $goalType,
            'target_weight_kg' => $targetWeightKg,
            'target_date' => $weeksToTarget ? $startDate->copy()->addWeeks((int) $weeksToTarget) : null,
            'notes' => is_string($notes) ? $notes : null,
        ]);
    }

    private function createReminders(User $user, mixed $waterTime, mixed $mealTime, mixed $workoutTime, mixed $checkinTime): void
    {
        $reminders = [
            'water' => ['time' => $waterTime, 'title' => 'Waktunya minum air', 'message' => 'Jangan lupa minum air ya!'],
            'meal' => ['time' => $mealTime, 'title' => 'Waktunya makan', 'message' => 'Saatnya makan sesuai rencana hari ini.'],
            'workout' => ['time' => $workoutTime, 'title' => 'Waktunya olahraga', 'message' => 'Yuk gerak badan sesuai rencana hari ini!'],
            'checkin' => ['time' => $checkinTime, 'title' => 'Check-in harian', 'message' => 'Sudah checklist progres hari ini?'],
        ];

        foreach ($reminders as $type => $reminder) {
            if (! $reminder['time']) {
                continue;
            }

            $user->reminders()->create([
                'type' => $type,
                'title' => $reminder['title'],
                'message' => $reminder['message'],
                'scheduled_at' => $reminder['time'],
                'is_recurring' => true,
                'recurrence_rule' => 'RRULE:FREQ=DAILY',
                'is_active' => true,
            ]);
        }
    }

    /**
     * Generates (or regenerates - idempotent, replaces same-date rows) the
     * meal plan, workout plan, daily tasks, and checklist for one date.
     */
    public function generateForDate(UserProgram $userProgram, string $date): void
    {
        $user = $userProgram->user;
        $ruleOutput = $this->ruleEngineService->evaluate($user);

        $this->ensureWeeklyPlan($userProgram, $date);

        $mealResult = $this->aiGateway->send($user, 'generatePlan', 'meal_plan', ['plan_date' => $date]);
        $workoutResult = $this->aiGateway->send($user, 'generatePlan', 'workout_plan', ['plan_date' => $date]);

        $mealUnavailable = (bool) ($mealResult['ai_unavailable'] ?? false);
        $workoutUnavailable = (bool) ($workoutResult['ai_unavailable'] ?? false);

        $mealPlanData = $mealUnavailable ? $this->fallbackMealPlan($ruleOutput) : $mealResult;
        $workoutPlanData = $workoutUnavailable ? $this->fallbackWorkoutPlan($ruleOutput, $user) : $workoutResult;

        DB::transaction(function () use ($userProgram, $date, $mealPlanData, $workoutPlanData, $ruleOutput, $mealUnavailable, $workoutUnavailable) {
            $userProgram->mealPlans()->whereDate('plan_date', $date)->get()->each->delete();
            $userProgram->workoutPlans()->whereDate('plan_date', $date)->get()->each->delete();
            $userProgram->dailyTasks()->whereDate('task_date', $date)->delete();
            $userProgram->checklistItems()->whereDate('item_date', $date)->delete();

            $checklistLabels = [];

            foreach ($mealPlanData['meal_plan'] ?? [] as $meal) {
                $mealPlan = $userProgram->mealPlans()->create([
                    'plan_date' => $date,
                    'meal_type' => $meal['meal_type'],
                    'total_calories' => $meal['total_calories'] ?? null,
                    'source' => $mealUnavailable ? 'rule_engine' : 'ai',
                ]);

                $names = [];
                foreach ($meal['items'] ?? [] as $item) {
                    $kbFoodId = KbFood::where('name_local', $item['name'])->value('id');
                    $mealPlan->items()->create([
                        'kb_food_id' => $kbFoodId,
                        'custom_name' => $kbFoodId ? null : ($item['name'] ?? null),
                        'portion' => $item['portion'] ?? 1,
                        'calories' => $item['calories'] ?? null,
                    ]);
                    $names[] = $item['name'] ?? null;
                }

                $label = ucfirst((string) $meal['meal_type']).': '.implode(' + ', array_filter($names));
                $checklistLabels[] = $label;

                $userProgram->dailyTasks()->create([
                    'task_date' => $date,
                    'task_type' => 'meal',
                    'title' => $label,
                    'source' => $mealUnavailable ? 'rule_engine' : 'ai',
                ]);
            }

            foreach ($workoutPlanData['workout_plan'] ?? [] as $index => $workout) {
                $workoutPlan = $userProgram->workoutPlans()->create([
                    'plan_date' => $date,
                    'workout_type' => $workout['type'] ?? null,
                    'duration_minutes' => $workout['duration_minutes'] ?? null,
                    'intensity' => $workout['intensity'] ?? 'low',
                    'source' => $workoutUnavailable ? 'rule_engine' : 'ai',
                ]);

                foreach ($workout['exercises'] ?? [] as $order => $exercise) {
                    $kbExerciseId = KbExercise::where('name', $exercise['name'])->value('id');
                    $workoutPlan->items()->create([
                        'kb_exercise_id' => $kbExerciseId,
                        'custom_name' => $kbExerciseId ? null : ($exercise['name'] ?? null),
                        'sets' => $exercise['sets'] ?? null,
                        'reps' => $exercise['reps'] ?? null,
                        'order' => $order,
                    ]);
                }

                $label = 'Olahraga: '.ucfirst((string) ($workout['type'] ?? 'umum')).' '.($workout['duration_minutes'] ?? '').' menit';
                $checklistLabels[] = $label;

                $userProgram->dailyTasks()->create([
                    'task_date' => $date,
                    'task_type' => 'workout',
                    'title' => $label,
                    'source' => $workoutUnavailable ? 'rule_engine' : 'ai',
                ]);
            }

            $waterLabel = "Minum air {$ruleOutput['water_target_ml']}ml";
            $checklistLabels[] = $waterLabel;
            $userProgram->dailyTasks()->create([
                'task_date' => $date,
                'task_type' => 'water',
                'title' => $waterLabel,
                'source' => 'rule_engine',
            ]);

            $userProgram->dailyTasks()->create([
                'task_date' => $date,
                'task_type' => 'checkin',
                'title' => 'Check-in harian',
                'source' => 'rule_engine',
            ]);

            foreach (array_filter($checklistLabels) as $label) {
                $userProgram->checklistItems()->create([
                    'item_date' => $date,
                    'label' => $label,
                ]);
            }
        });
    }

    private function ensureWeeklyPlan(UserProgram $userProgram, string $date): WeeklyPlan
    {
        $startDate = Carbon::parse($userProgram->start_date);
        $target = Carbon::parse($date);
        $weekNumber = (int) floor($startDate->diffInDays($target) / 7) + 1;
        $weekStart = $startDate->copy()->addDays(($weekNumber - 1) * 7);
        $weekEnd = $weekStart->copy()->addDays(6);

        return $userProgram->weeklyPlans()->firstOrCreate(
            ['week_number' => $weekNumber],
            ['start_date' => $weekStart, 'end_date' => $weekEnd, 'generated_by' => 'rule_engine'],
        );
    }

    /**
     * "Rule Engine + last-known-good plan always available offline of AI"
     * (RuleEngineService docblock) - a simple deterministic plan built
     * straight from the KB and calorie_target, used whenever no AI
     * provider is configured or all configured providers failed.
     */
    private function fallbackMealPlan(array $ruleOutput): array
    {
        $restrictions = collect($ruleOutput['restrictions'] ?? []);
        $eligible = fn (Collection $foods) => $foods->reject(fn (KbFood $f) => $restrictions->intersect($f->tags ?? [])->isNotEmpty());
        $pick = fn (string $category) => $eligible(KbFood::where('category', $category)->get())->first();

        $splits = ['breakfast' => 0.25, 'lunch' => 0.35, 'dinner' => 0.30, 'snack' => 0.10];
        $mealPlan = [];

        foreach ($splits as $type => $pct) {
            $foods = $type === 'snack'
                ? collect([$pick('fruit') ?? $pick('snack')])->filter()
                : collect(['staple', 'protein', 'vegetable'])->map($pick)->filter();

            $mealPlan[] = [
                'meal_type' => $type,
                'items' => $foods->map(fn (KbFood $f) => ['name' => $f->name_local, 'portion' => 1, 'calories' => (float) $f->calories])->values()->all(),
                'total_calories' => (float) round($ruleOutput['calorie_target'] * $pct),
            ];
        }

        return [
            'summary' => 'Rencana makan dasar dari Rule Engine (AI tidak tersedia).',
            'meal_plan' => $mealPlan,
            'motivation' => 'Tetap konsisten dengan rencana dasar ini, kamu pasti bisa!',
        ];
    }

    private function fallbackWorkoutPlan(array $ruleOutput, User $user): array
    {
        $diseaseSlugs = $user->diseases()->with('disease:id,slug')->get()->pluck('disease.slug')->filter()->values();

        $exercises = KbExercise::where('difficulty', $ruleOutput['workout_level'] ?? 'beginner')
            ->get()
            ->reject(fn (KbExercise $e) => collect($e->contraindications ?? [])->intersect($diseaseSlugs)->isNotEmpty())
            ->take(4)
            ->values();

        return [
            'summary' => 'Rencana olahraga dasar dari Rule Engine (AI tidak tersedia).',
            'workout_plan' => [[
                'type' => 'campuran',
                'exercises' => $exercises->map(fn (KbExercise $e) => ['name' => $e->name, 'sets' => 3, 'reps' => 12])->all(),
                'duration_minutes' => 30,
                'intensity' => 'low',
            ]],
            'motivation' => 'Gerak sedikit tetap lebih baik daripada tidak sama sekali!',
        ];
    }
}
