<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthScore extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'scored_at',
        'score',
        'breakdown',
        'explanation',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'scored_at' => 'date:Y-m-d',
            'breakdown' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
