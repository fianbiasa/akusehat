<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDisease extends Model
{
    protected $fillable = [
        'user_id',
        'kb_disease_id',
        'diagnosed_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'diagnosed_at' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function disease(): BelongsTo
    {
        return $this->belongsTo(KbDisease::class, 'kb_disease_id');
    }
}
