<?php

namespace Tests\Feature\Coach;

use App\Models\Role;
use App\Models\User;
use App\Services\Coach\CoachAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachNoteControllerTest extends TestCase
{
    use RefreshDatabase;

    private function coach(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'coach')->value('id')]);
    }

    public function test_a_note_defaults_to_not_visible_to_the_member()
    {
        $coach = $this->coach();
        $member = User::factory()->create();
        app(CoachAssignmentService::class)->assign($coach, $member);

        $this->actingAs($coach)->post("/coach/members/{$member->id}/notes", [
            'note' => 'Sempat cerita pola makan malam sering telat.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coach_notes', ['coach_id' => $coach->id, 'member_id' => $member->id, 'is_visible_to_member' => false]);
    }

    public function test_a_coach_can_flag_a_note_visible_to_the_member()
    {
        $coach = $this->coach();
        $member = User::factory()->create();
        app(CoachAssignmentService::class)->assign($coach, $member);
        $note = $coach->coachNotesWritten()->create(['member_id' => $member->id, 'note' => 'x', 'is_visible_to_member' => false]);

        $this->actingAs($coach)->patch("/coach/notes/{$note->id}", ['is_visible_to_member' => true])->assertSessionHasNoErrors();

        $this->assertTrue($note->fresh()->is_visible_to_member);
    }

    public function test_a_coach_cannot_add_a_note_for_an_unassigned_member()
    {
        $coach = $this->coach();
        $member = User::factory()->create();

        $this->actingAs($coach)->post("/coach/members/{$member->id}/notes", ['note' => 'x'])->assertForbidden();
    }

    public function test_a_coach_cannot_edit_another_coachs_note()
    {
        $coachA = $this->coach();
        $coachB = $this->coach();
        $member = User::factory()->create();
        $note = $coachA->coachNotesWritten()->create(['member_id' => $member->id, 'note' => 'x', 'is_visible_to_member' => false]);

        $this->actingAs($coachB)->patch("/coach/notes/{$note->id}", ['is_visible_to_member' => true])->assertForbidden();
    }
}
