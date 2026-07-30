<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachNote extends Model
{
    protected $fillable = [
        'coach_id',
        'member_id',
        'note',
        'is_visible_to_member',
    ];

    protected function casts(): array
    {
        return [
            'is_visible_to_member' => 'boolean',
        ];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }
}
