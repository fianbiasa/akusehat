<?php

namespace App\Notifications;

use App\Models\AiRecommendation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A Coach reviewing a recommendation is a meaningful, infrequent
 * event for the Member (unlike ReminderDue) - worth an email. Queued
 * so SMTP delivery never blocks the Coach's approve/reject request.
 */
class RecommendationReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private AiRecommendation $recommendation) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $applied = $this->recommendation->status === 'applied';

        return (new MailMessage)
            ->subject($applied ? 'Programmu diperbarui oleh Coach' : 'Coach meninjau rekomendasi AI-mu')
            ->greeting("Halo, {$notifiable->name}!")
            ->line($applied
                ? 'Coach kamu menyetujui salah satu rekomendasi AI dan memperbarui programmu.'
                : 'Coach kamu meninjau salah satu rekomendasi AI untukmu.')
            ->action('Lihat Program', url('/dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'recommendation_id' => $this->recommendation->id,
            'status' => $this->recommendation->status,
            'message' => $this->recommendation->status === 'applied'
                ? 'Program kamu diperbarui oleh Coach.'
                : 'Coach meninjau salah satu rekomendasi AI untuk kamu.',
        ];
    }
}
