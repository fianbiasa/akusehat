<?php

namespace App\Notifications;

use App\Models\UserProgram;
use Illuminate\Notifications\Notification;

class ProgramReady extends Notification
{
    public function __construct(private UserProgram $userProgram) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'user_program_id' => $this->userProgram->id,
            'message' => 'Program kamu sudah siap! Cek rencana hari ini di dashboard.',
        ];
    }
}
