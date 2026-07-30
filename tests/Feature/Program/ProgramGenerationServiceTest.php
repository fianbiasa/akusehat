<?php

namespace Tests\Feature\Program;

use App\Models\AiProvider;
use App\Models\AiRequestLog;
use App\Models\Program;
use App\Models\User;
use App\Models\UserProgram;
use App\Services\Program\ProgramGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProgramGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function profiledUser(): User
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $user->healthProfile()->create([
            'gender' => 'male',
            'date_of_birth' => now()->subYears(30),
            'height_cm' => 170,
            'initial_weight_kg' => 80,
            'bmi' => 27.7,
            'bmr' => 1700,
            'tdee' => 2000,
        ]);
        $user->lifestyleProfile()->create(['activity_level' => 'light']);

        return $user->fresh();
    }

    private function activeUserProgram(User $user): UserProgram
    {
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();

        return $user->programs()->create([
            'program_id' => $program->id,
            'status' => 'active',
            'start_date' => today(),
            'end_date' => today()->addDays(89),
            'created_by' => 'ai',
        ]);
    }

    public function test_generation_falls_back_to_the_rule_engine_when_no_ai_provider_is_configured()
    {
        $user = $this->profiledUser();
        $userProgram = $this->activeUserProgram($user);

        app(ProgramGenerationService::class)->generateForDate($userProgram, today()->toDateString());

        $this->assertSame(4, $userProgram->mealPlans()->count());
        $this->assertSame(1, $userProgram->workoutPlans()->count());
        $this->assertSame('rule_engine', $userProgram->mealPlans()->first()->source);
        $this->assertSame('rule_engine', $userProgram->workoutPlans()->first()->source);
        $this->assertGreaterThan(0, $userProgram->dailyTasks()->count());
        $this->assertGreaterThan(0, $userProgram->checklistItems()->count());
        $this->assertSame(1, $userProgram->weeklyPlans()->count());
    }

    public function test_generation_persists_the_ai_response_and_matches_known_kb_items_by_name()
    {
        $user = $this->profiledUser();
        $userProgram = $this->activeUserProgram($user);

        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $model = $provider->models()->firstOrFail();
        $user->aiSettings()->create(['provider_id' => $provider->id, 'model_id' => $model->id, 'is_default' => true, 'api_key' => 'sk-test']);

        $mealJson = json_encode([
            'summary' => 'Test meal plan',
            'meal_plan' => [
                ['meal_type' => 'breakfast', 'items' => [['name' => 'Nasi Merah', 'portion' => 1, 'calories' => 110]], 'total_calories' => 110],
            ],
            'motivation' => 'Semangat!',
        ]);
        $workoutJson = json_encode([
            'summary' => 'Test workout plan',
            'workout_plan' => [
                ['type' => 'cardio', 'exercises' => [['name' => 'Jalan Kaki (Brisk Walk)', 'sets' => 1, 'reps' => 1]], 'duration_minutes' => 30, 'intensity' => 'low'],
            ],
            'motivation' => 'Ayo gerak!',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => $mealJson]]]], 200)
                ->push(['choices' => [['message' => ['content' => $workoutJson]]]], 200),
        ]);

        app(ProgramGenerationService::class)->generateForDate($userProgram, today()->toDateString());

        $mealPlan = $userProgram->mealPlans()->with('items')->first();
        $this->assertSame('ai', $mealPlan->source);
        $this->assertNotNull($mealPlan->items->first()->kb_food_id);

        $workoutPlan = $userProgram->workoutPlans()->with('items')->first();
        $this->assertSame('ai', $workoutPlan->source);
        $this->assertNotNull($workoutPlan->items->first()->kb_exercise_id);

        $this->assertSame(2, AiRequestLog::count());
    }

    public function test_regenerating_the_same_date_replaces_rather_than_duplicates_rows()
    {
        $user = $this->profiledUser();
        $userProgram = $this->activeUserProgram($user);
        $date = today()->toDateString();

        $service = app(ProgramGenerationService::class);
        $service->generateForDate($userProgram, $date);
        $firstCount = $userProgram->mealPlans()->count();

        $service->generateForDate($userProgram, $date);

        $this->assertSame($firstCount, $userProgram->mealPlans()->count());
    }
}
