<?php

namespace App\Notifications;

use App\Models\AiRecommendation;
use Illuminate\Notifications\Notification;

class RecommendationPendingApproval extends Notification
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
            'type' => $this->recommendation->type,
            'member_id' => $this->recommendation->user_id,
            'message' => 'Ada rekomendasi AI yang menunggu persetujuan kamu.',
        ];
    }
}
