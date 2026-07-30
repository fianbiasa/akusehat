<?php

namespace Tests\Unit\Notifications;

use App\Models\Achievement;
use App\Models\AiRecommendation;
use App\Models\Program;
use App\Models\User;
use App\Models\UserProgram;
use App\Notifications\AchievementEarned;
use App\Notifications\ProgramReady;
use App\Notifications\RecommendationReviewed;
use App\Notifications\ReminderDue;
use App\Models\Reminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins down which notifications are mail-worthy (one-time/significant
 * events) vs database-only (frequent/routine, per ReminderDue's
 * already-documented reasoning) - a design decision made in Phase 10
 * when the real SMTP channel first became usable.
 */
class MailChannelNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_achievement_earned_is_sent_via_database_and_mail()
    {
        $achievement = Achievement::first();
        $notification = new AchievementEarned($achievement);

        $this->assertSame(['database', 'mail'], $notification->via(new User));
        $this->assertStringContainsString($achievement->name, $notification->toMail(new User(['name' => 'Test']))->subject);
    }

    public function test_program_ready_is_sent_via_database_and_mail()
    {
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $userProgram = UserProgram::create(['user_id' => User::factory()->create()->id, 'program_id' => $program->id, 'status' => 'active', 'start_date' => today(), 'created_by' => 'ai']);

        $notification = new ProgramReady($userProgram);

        $this->assertSame(['database', 'mail'], $notification->via(new User));
        $this->assertNotNull($notification->toMail(new User(['name' => 'Test']))->subject);
    }

    public function test_recommendation_reviewed_is_sent_via_database_and_mail()
    {
        $user = User::factory()->create();
        $recommendation = AiRecommendation::create(['user_id' => $user->id, 'type' => 'meal_adjustment', 'content' => ['detail' => 'x'], 'status' => 'applied']);

        $notification = new RecommendationReviewed($recommendation);

        $this->assertSame(['database', 'mail'], $notification->via(new User));
        $this->assertNotNull($notification->toMail(new User(['name' => 'Test']))->subject);
    }

    public function test_reminder_due_stays_database_only()
    {
        $user = User::factory()->create();
        $reminder = Reminder::create(['user_id' => $user->id, 'type' => 'water', 'title' => 'Minum air', 'scheduled_at' => '10:00:00']);

        $notification = new ReminderDue($reminder);

        $this->assertSame(['database'], $notification->via(new User));
    }
}
