<?php

namespace Tests\Feature\Program;

use App\Events\AIRecommendationCreated;
use App\Models\Program;
use App\Models\User;
use App\Models\UserProgram;
use App\Services\Program\RecommendationApplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RecommendationApplierServiceTest extends TestCase
{
    use RefreshDatabase;

    private function userProgram(): UserProgram
    {
        $user = User::factory()->create();
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();

        return $user->programs()->create([
            'program_id' => $program->id,
            'status' => 'active',
            'start_date' => today(),
            'end_date' => today()->addDays(89),
            'created_by' => 'ai',
        ]);
    }

    public function test_a_habit_adjustment_marked_auto_applicable_is_applied_immediately()
    {
        $userProgram = $this->userProgram();

        app(RecommendationApplierService::class)->applyAdjustments($userProgram, [
            ['type' => 'habit', 'detail' => 'Minum air lebih sering di pagi hari.', 'auto_applicable' => true],
        ]);

        $this->assertDatabaseHas('ai_recommendations', [
            'user_program_id' => $userProgram->id,
            'type' => 'habit',
            'status' => 'applied',
        ]);
    }

    public function test_a_workout_adjustment_never_auto_applies_even_when_the_ai_marks_it_auto_applicable()
    {
        Event::fake([AIRecommendationCreated::class]);
        $userProgram = $this->userProgram();

        app(RecommendationApplierService::class)->applyAdjustments($userProgram, [
            ['type' => 'workout_adjustment', 'detail' => 'Naikkan reps push up dari 10 ke 20.', 'auto_applicable' => true],
        ]);

        $this->assertDatabaseHas('ai_recommendations', [
            'user_program_id' => $userProgram->id,
            'type' => 'workout_adjustment',
            'status' => 'pending',
        ]);
        Event::assertDispatched(AIRecommendationCreated::class);
    }

    public function test_a_meal_adjustment_not_marked_auto_applicable_stays_pending()
    {
        Event::fake([AIRecommendationCreated::class]);
        $userProgram = $this->userProgram();

        app(RecommendationApplierService::class)->applyAdjustments($userProgram, [
            ['type' => 'meal_adjustment', 'detail' => 'Kurangi porsi nasi malam.', 'auto_applicable' => false],
        ]);

        $this->assertDatabaseHas('ai_recommendations', [
            'user_program_id' => $userProgram->id,
            'type' => 'meal_adjustment',
            'status' => 'pending',
        ]);
        Event::assertDispatched(AIRecommendationCreated::class);
    }
}
