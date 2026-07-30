<?php

namespace Tests\Feature\Program;

use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_renders_with_no_active_program()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_the_dashboard_renders_with_an_active_program_and_todays_checklist()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $userProgram = $user->programs()->create([
            'program_id' => $program->id, 'status' => 'active',
            'start_date' => today(), 'end_date' => today()->addDays(89), 'created_by' => 'ai',
        ]);
        $userProgram->checklistItems()->create(['item_date' => today(), 'label' => 'Minum air 2000ml']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('checklist', 1)->has('activePrograms', 1));
    }

    /**
     * Regression test: manually rebuilding a response array from a
     * date-cast Eloquent attribute (rather than serializing the model
     * itself) bypasses the 'date:Y-m-d' cast format - Carbon's own
     * default JSON serialization (full ISO8601) takes over instead.
     * Caught via live HTTP smoke testing on Phase 7's ProgressPhoto
     * endpoints; this asserts the same class of bug can't reappear here.
     */
    public function test_active_program_dates_are_plain_y_m_d_strings_not_full_timestamps()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $user->programs()->create([
            'program_id' => $program->id, 'status' => 'active',
            'start_date' => today(), 'end_date' => today()->addDays(89), 'created_by' => 'ai',
        ]);
        $user->weightLogs()->create(['logged_at' => today(), 'weight_kg' => 75, 'created_at' => now()]);
        $user->healthScores()->create(['scored_at' => today(), 'score' => 80, 'breakdown' => [], 'created_at' => now()]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->where('activePrograms.0.start_date', today()->toDateString())
            ->where('activePrograms.0.end_date', today()->addDays(89)->toDateString())
            ->where('latestMeasurement.measured_at', today()->toDateString())
            ->where('healthScore.scored_at', today()->toDateString())
        );
    }

    public function test_switching_the_program_query_param_selects_that_program()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $first = $user->programs()->create([
            'program_id' => $program->id, 'status' => 'active',
            'start_date' => today(), 'end_date' => today()->addDays(89), 'created_by' => 'ai',
        ]);
        $second = $user->programs()->create([
            'program_id' => $program->id, 'status' => 'active',
            'start_date' => today(), 'end_date' => today()->addDays(89), 'created_by' => 'ai',
        ]);

        $response = $this->actingAs($user)->get("/dashboard?program={$second->id}");

        $response->assertInertia(fn ($page) => $page->where('selectedProgramId', $second->id));
        $this->assertNotEquals($first->id, $second->id);
    }
}
