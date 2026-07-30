<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressPhoto extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'logged_at',
        'angle',
        'photo_path',
        'is_private',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'date:Y-m-d',
            'is_private' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
