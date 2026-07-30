<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachProfile extends Model
{
    protected $fillable = [
        'user_id',
        'bio',
        'specialization',
        'certification',
        'max_members',
        'rating_avg',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
