<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingAnswer extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'onboarding_session_id',
        'question_id',
        'answer_value',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'answer_value' => 'array',
            'answered_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(OnboardingSession::class, 'onboarding_session_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(OnboardingQuestion::class, 'question_id');
    }
}
