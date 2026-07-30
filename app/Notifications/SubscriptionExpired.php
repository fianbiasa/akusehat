<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpired extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Subscription $subscription) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Langgananmu telah berakhir')
            ->greeting("Halo, {$notifiable->name}!")
            ->line("Paket {$this->subscription->plan->name} kamu sudah berakhir. Akunmu otomatis kembali ke paket Gratis.")
            ->action('Lihat Paket Langganan', url('/settings/subscription'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'message' => "Langganan {$this->subscription->plan->name} kamu telah berakhir.",
        ];
    }
}
