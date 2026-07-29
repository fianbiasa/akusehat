<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodyMeasurement extends Model
{
    protected $fillable = [
        'user_id',
        'measured_at',
        'weight_kg',
        'waist_cm',
        'chest_cm',
        'hip_cm',
        'arm_cm',
        'thigh_cm',
        'body_fat_pct',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
