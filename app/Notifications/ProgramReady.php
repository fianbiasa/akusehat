<?php

namespace App\Notifications;

use App\Models\UserProgram;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A one-time event per program (unlike ReminderDue's per-occurrence
 * cadence) - worth an email in addition to the in-app bell. Queued so
 * SMTP delivery never blocks the request/job that triggered it.
 */
class ProgramReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private UserProgram $userProgram) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Program kamu sudah siap!')
            ->greeting("Halo, {$notifiable->name}!")
            ->line('Program kesehatanmu sudah selesai dibuat dan siap dijalani hari ini.')
            ->action('Lihat Dashboard', url('/dashboard'))
            ->line('Semangat memulai perjalananmu!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'user_program_id' => $this->userProgram->id,
            'message' => 'Program kamu sudah siap! Cek rencana hari ini di dashboard.',
        ];
    }
}
