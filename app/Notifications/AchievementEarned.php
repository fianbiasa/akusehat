<?php

namespace App\Notifications;

use App\Models\Achievement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A one-time, celebratory event (unlike ReminderDue's per-occurrence
 * cadence) - worth an email in addition to the in-app bell. Queued
 * since SMTP delivery must never block the request/job that triggered
 * it (04-Architecture.md's async-AI-call reasoning applies equally to
 * any outbound network call on the happy path).
 */
class AchievementEarned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Achievement $achievement) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Pencapaian baru: {$this->achievement->name}")
            ->greeting("Selamat, {$notifiable->name}!")
            ->line("Kamu baru saja meraih pencapaian: {$this->achievement->name}.")
            ->line($this->achievement->description ?? '')
            ->action('Lihat Pencapaian', url('/profile/achievements'))
            ->line('Terus semangat menjalani programmu!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'achievement_id' => $this->achievement->id,
            'name' => $this->achievement->name,
            'message' => "Kamu meraih pencapaian baru: {$this->achievement->name}!",
        ];
    }
}
