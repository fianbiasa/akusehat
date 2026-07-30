<?php

namespace App\Notifications;

use App\Models\AiRecommendation;
use Illuminate\Notifications\Notification;

class RecommendationReviewed extends Notification
{
    public function __construct(private AiRecommendation $recommendation) {}

    public function via(object $notifiable): array
    {
        return ['database'];
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
