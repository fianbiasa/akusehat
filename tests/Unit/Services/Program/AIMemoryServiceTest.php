<?php

namespace Tests\Unit\Services\Program;

use App\Models\Program;
use App\Models\User;
use App\Models\UserProgram;
use App\Services\Program\AIMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIMemoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function userProgramFor(User $user): UserProgram
    {
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();

        return $user->programs()->create([
            'program_id' => $program->id, 'status' => 'active',
            'start_date' => today()->subDays(6), 'end_date' => today()->addDays(83), 'created_by' => 'ai',
        ]);
    }

    public function test_seven_fully_completed_days_creates_a_milestone_memory()
    {
        $user = User::factory()->create();
        $userProgram = $this->userProgramFor($user);

        for ($i = 0; $i < 7; $i++) {
            $userProgram->checklistItems()->create([
                'item_date' => today()->subDays($i), 'label' => 'Minum air', 'is_checked' => true, 'checked_at' => now(),
            ]);
        }

        app(AIMemoryService::class)->scan($user);

        $this->assertDatabaseHas('ai_memories', ['user_id' => $user->id, 'memory_type' => 'milestone']);
    }

    public function test_three_zero_completion_days_creates_a_concern_memory()
    {
        $user = User::factory()->create();
        $userProgram = $this->userProgramFor($user);

        for ($i = 0; $i < 3; $i++) {
            $userProgram->checklistItems()->create([
                'item_date' => today()->subDays($i), 'label' => 'Minum air', 'is_checked' => false,
            ]);
        }

        app(AIMemoryService::class)->scan($user);

        $this->assertDatabaseHas('ai_memories', ['user_id' => $user->id, 'memory_type' => 'concern']);
    }

    public function test_a_mixed_completion_week_creates_neither_milestone_nor_concern()
    {
        $user = User::factory()->create();
        $userProgram = $this->userProgramFor($user);

        for ($i = 0; $i < 7; $i++) {
            $userProgram->checklistItems()->create([
                'item_date' => today()->subDays($i), 'label' => 'Minum air', 'is_checked' => $i % 2 === 0,
            ]);
        }

        app(AIMemoryService::class)->scan($user);

        $this->assertDatabaseMissing('ai_memories', ['user_id' => $user->id, 'memory_type' => 'milestone']);
        $this->assertDatabaseMissing('ai_memories', ['user_id' => $user->id, 'memory_type' => 'concern']);
    }

    public function test_scanning_twice_in_one_day_does_not_duplicate_the_memory()
    {
        $user = User::factory()->create();
        $userProgram = $this->userProgramFor($user);

        for ($i = 0; $i < 7; $i++) {
            $userProgram->checklistItems()->create([
                'item_date' => today()->subDays($i), 'label' => 'Minum air', 'is_checked' => true, 'checked_at' => now(),
            ]);
        }

        $service = app(AIMemoryService::class);
        $service->scan($user);
        $service->scan($user);

        $this->assertSame(1, $user->aiMemories()->where('memory_type', 'milestone')->count());
    }

    public function test_a_significant_weight_drop_creates_a_trend_memory()
    {
        $user = User::factory()->create();
        $this->userProgramFor($user);

        $user->bodyMeasurements()->create(['measured_at' => today()->subDays(13), 'weight_kg' => 80]);
        $user->bodyMeasurements()->create(['measured_at' => today(), 'weight_kg' => 78.5]);

        app(AIMemoryService::class)->scan($user);

        $this->assertDatabaseHas('ai_memories', ['user_id' => $user->id, 'memory_type' => 'trend']);
    }
}
