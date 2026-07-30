<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    private function coach(): User
    {
        $coach = User::factory()->create(['role_id' => Role::where('name', 'coach')->value('id')]);
        $coach->coachProfile()->create([]);

        return $coach;
    }

    private function programWithCoach(User $member, User $coach)
    {
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();

        return $member->programs()->create([
            'program_id' => $program->id, 'coach_id' => $coach->id, 'status' => 'active',
            'start_date' => today(), 'end_date' => today()->addDays(89), 'created_by' => 'ai',
        ]);
    }

    public function test_a_member_can_review_their_assigned_coach()
    {
        $coach = $this->coach();
        $member = User::factory()->create();
        $userProgram = $this->programWithCoach($member, $coach);

        $this->actingAs($member)->post("/user-programs/{$userProgram->id}/review", [
            'rating' => 5,
            'comment' => 'Sangat membantu!',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reviews', ['coach_id' => $coach->id, 'member_id' => $member->id, 'rating' => 5]);
    }

    public function test_submitting_a_second_review_updates_the_existing_one_rather_than_duplicating()
    {
        $coach = $this->coach();
        $member = User::factory()->create();
        $userProgram = $this->programWithCoach($member, $coach);

        $this->actingAs($member)->post("/user-programs/{$userProgram->id}/review", ['rating' => 3]);
        $this->actingAs($member)->post("/user-programs/{$userProgram->id}/review", ['rating' => 5]);

        $this->assertSame(1, Review::where('coach_id', $coach->id)->where('member_id', $member->id)->count());
        $this->assertSame(5, Review::where('coach_id', $coach->id)->where('member_id', $member->id)->value('rating'));
    }

    public function test_the_coachs_rating_average_is_recalculated_on_a_new_review()
    {
        $coach = $this->coach();
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $programA = $this->programWithCoach($memberA, $coach);
        $programB = $this->programWithCoach($memberB, $coach);

        $this->actingAs($memberA)->post("/user-programs/{$programA->id}/review", ['rating' => 4]);
        $this->actingAs($memberB)->post("/user-programs/{$programB->id}/review", ['rating' => 2]);

        $this->assertEquals(3.0, $coach->coachProfile->fresh()->rating_avg);
    }

    public function test_a_member_cannot_review_a_program_with_no_assigned_coach()
    {
        $member = User::factory()->create();
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $userProgram = $member->programs()->create([
            'program_id' => $program->id, 'status' => 'active',
            'start_date' => today(), 'end_date' => today()->addDays(89), 'created_by' => 'ai',
        ]);

        $this->actingAs($member)->post("/user-programs/{$userProgram->id}/review", ['rating' => 5])->assertStatus(422);
    }

    public function test_a_member_cannot_review_on_behalf_of_another_members_program()
    {
        $coach = $this->coach();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $userProgram = $this->programWithCoach($owner, $coach);

        $this->actingAs($intruder)->post("/user-programs/{$userProgram->id}/review", ['rating' => 1])->assertForbidden();
    }
}
