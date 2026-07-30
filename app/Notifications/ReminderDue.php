<?php

namespace App\Notifications;

use App\Models\Reminder;
use Illuminate\Notifications\Notification;

/**
 * Database-only (in-app bell icon per wireframe/dashboard.md) - no
 * push/SMS/email channel is wired up in this environment. `via()`
 * intentionally omits 'mail' since reminders.type already covers
 * water/meal/workout/checkin cadence that doesn't warrant an email per
 * occurrence.
 */
class ReminderDue extends Notification
{
    public function __construct(private Reminder $reminder) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'reminder_id' => $this->reminder->id,
            'type' => $this->reminder->type,
            'title' => $this->reminder->title,
            'message' => $this->reminder->message,
        ];
    }
}
