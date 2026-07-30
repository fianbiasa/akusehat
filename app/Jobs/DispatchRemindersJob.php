<?php

namespace App\Jobs;

use App\Models\Reminder;
use App\Notifications\ReminderDue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Scheduled every minute (04-Architecture.md §6). scheduled_at is a
 * TIME-of-day (no date), so "due" means "matches the current minute in
 * the owning user's timezone" - last_sent_at is the dedup guard so a
 * daily-recurring reminder fires once per day, not once per minute it
 * happens to still match after the job runs slightly late.
 */
class DispatchRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Reminder::where('is_active', true)->with('user')->chunkById(200, function ($reminders) {
            foreach ($reminders as $reminder) {
                $this->dispatchIfDue($reminder);
            }
        });
    }

    private function dispatchIfDue(Reminder $reminder): void
    {
        $user = $reminder->user;
        $nowInUserTz = Carbon::now($user->timezone);

        if ($nowInUserTz->format('H:i') !== Carbon::parse($reminder->scheduled_at)->format('H:i')) {
            return;
        }

        if ($reminder->last_sent_at && Carbon::parse($reminder->last_sent_at)->setTimezone($user->timezone)->isSameDay($nowInUserTz)) {
            return;
        }

        $user->notify(new ReminderDue($reminder));
        $reminder->update(['last_sent_at' => now()]);
    }
}
