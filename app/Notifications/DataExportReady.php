<?php

namespace App\Notifications;

use App\Models\DataExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * A one-time, user-requested compliance event - mail-worthy per the
 * Phase 10 precedent (significant/infrequent, unlike ReminderDue).
 * The signed link expires in 24h (longer than progress photos' 30min,
 * since this is a "download when convenient" flow, not a live image).
 */
class DataExportReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private DataExport $export) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute('data-export.download', now()->addHours(24), ['export' => $this->export->id]);

        return (new MailMessage)
            ->subject('Data kamu siap diunduh')
            ->greeting("Halo, {$notifiable->name}!")
            ->line('Ekspor data pribadimu sudah siap.')
            ->action('Unduh Data', $url)
            ->line('Tautan ini berlaku selama 24 jam.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'export_id' => $this->export->id,
            'message' => 'Ekspor data kamu sudah siap diunduh.',
        ];
    }
}
