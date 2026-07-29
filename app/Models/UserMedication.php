<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMedication extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'dosage',
        'frequency',
        'started_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
