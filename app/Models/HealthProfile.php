<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthProfile extends Model
{
    protected $fillable = [
        'user_id',
        'date_of_birth',
        'gender',
        'height_cm',
        'initial_weight_kg',
        'blood_type',
        'bmi',
        'bmr',
        'tdee',
    ];

    protected function casts(): array
    {
        return [
            // Plain Y-m-d, not Laravel's default full ISO8601 timestamp -
            // a birthdate isn't a moment in time, and the default format
            // (with UTC 'Z' conversion) can shift the date by a day
            // relative to APP_TIMEZONE and won't populate <input type=date>.
            'date_of_birth' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
