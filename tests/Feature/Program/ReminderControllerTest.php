<?php

namespace Tests\Feature\Program;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_create_a_reminder()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $this->actingAs($user)->post('/reminders', [
            'type' => 'water',
            'title' => 'Minum air',
            'scheduled_at' => '09:00',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reminders', ['user_id' => $user->id, 'type' => 'water']);
    }

    public function test_a_member_cannot_update_another_members_reminder()
    {
        $owner = User::factory()->create(['onboarding_completed_at' => now()]);
        $intruder = User::factory()->create(['onboarding_completed_at' => now()]);
        $reminder = $owner->reminders()->create([
            'type' => 'water', 'title' => 'Minum air', 'scheduled_at' => '09:00',
            'is_recurring' => true, 'recurrence_rule' => 'RRULE:FREQ=DAILY', 'is_active' => true,
        ]);

        $this->actingAs($intruder)->patch("/reminders/{$reminder->id}", ['is_active' => false])->assertForbidden();
    }

    public function test_a_member_can_delete_their_own_reminder()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $reminder = $user->reminders()->create([
            'type' => 'water', 'title' => 'Minum air', 'scheduled_at' => '09:00',
            'is_recurring' => true, 'recurrence_rule' => 'RRULE:FREQ=DAILY', 'is_active' => true,
        ]);

        $this->actingAs($user)->delete("/reminders/{$reminder->id}")->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('reminders', ['id' => $reminder->id]);
    }
}
