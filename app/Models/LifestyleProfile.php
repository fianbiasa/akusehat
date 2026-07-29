<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LifestyleProfile extends Model
{
    protected $fillable = [
        'user_id',
        'activity_level',
        'sleep_time',
        'wake_time',
        'avg_sleep_hours',
        'work_hours_per_day',
        'diet_pattern',
        'sugary_drinks_frequency',
        'smoking_status',
        'alcohol_frequency',
        'exercise_frequency',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
