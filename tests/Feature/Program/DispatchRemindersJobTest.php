<?php

namespace Tests\Feature\Program;

use App\Jobs\DispatchRemindersJob;
use App\Models\User;
use App\Notifications\ReminderDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DispatchRemindersJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reminder_due_this_minute_is_sent_and_marks_last_sent_at()
    {
        Notification::fake();
        $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
        $now = Carbon::now($user->timezone);

        $reminder = $user->reminders()->create([
            'type' => 'water', 'title' => 'Minum air', 'scheduled_at' => $now->format('H:i'),
            'is_recurring' => true, 'is_active' => true,
        ]);

        (new DispatchRemindersJob)->handle();

        Notification::assertSentTo($user, ReminderDue::class);
        $this->assertNotNull($reminder->fresh()->last_sent_at);
    }

    public function test_a_reminder_already_sent_today_is_not_sent_again()
    {
        Notification::fake();
        $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
        $now = Carbon::now($user->timezone);

        $user->reminders()->create([
            'type' => 'water', 'title' => 'Minum air', 'scheduled_at' => $now->format('H:i'),
            // last_sent_at must be written the same way the real job writes
            // it (plain now(), i.e. relative to config('app.timezone')) -
            // Eloquent's datetime cast stores/reads back a bare wall-clock
            // string with no embedded timezone, so writing this fixture in
            // the user's timezone instead only round-trips correctly by
            // coincidence when config('app.timezone') === $user->timezone.
            // It silently breaks whenever they differ (e.g. CI's
            // .env.example defaults APP_TIMEZONE to UTC) - not a day-of-run
            // fluke, a real test/production mismatch.
            'is_recurring' => true, 'is_active' => true, 'last_sent_at' => now(),
        ]);

        (new DispatchRemindersJob)->handle();

        Notification::assertNothingSent();
    }

    public function test_a_reminder_not_due_yet_is_not_sent()
    {
        Notification::fake();
        $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
        $farFuture = Carbon::now($user->timezone)->addHours(3)->format('H:i');

        $user->reminders()->create([
            'type' => 'meal', 'title' => 'Waktunya makan', 'scheduled_at' => $farFuture,
            'is_recurring' => true, 'is_active' => true,
        ]);

        (new DispatchRemindersJob)->handle();

        Notification::assertNothingSent();
    }
}
