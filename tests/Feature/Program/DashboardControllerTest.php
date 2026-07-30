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
