<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired once, exactly 7 days before ends_at (SubscriptionRenewalCheckJob's
 * daily whereBetween window naturally only matches one day per period -
 * no separate "already notified" flag needed).
 */
class SubscriptionExpiringSoon extends Notification implements ShouldQueue
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
            ->subject('Langgananmu akan berakhir dalam 7 hari')
            ->greeting("Halo, {$notifiable->name}!")
            ->line("Paket {$this->subscription->plan->name} kamu akan berakhir pada {$this->subscription->ends_at->translatedFormat('d F Y')}.")
            ->line('Perpanjang sekarang agar akses Coach dan fitur premium tidak terputus.')
            ->action('Kelola Langganan', url('/settings/subscription'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'ends_at' => $this->subscription->ends_at?->toDateString(),
            'message' => "Langganan {$this->subscription->plan->name} kamu akan berakhir dalam 7 hari.",
        ];
    }
}
