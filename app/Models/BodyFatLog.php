<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodyFatLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'logged_at',
        'body_fat_pct',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'date:Y-m-d',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
