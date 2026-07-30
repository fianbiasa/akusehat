<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyPlan extends Model
{
    protected $fillable = [
        'user_program_id',
        'week_number',
        'start_date',
        'end_date',
        'ai_summary',
        'ai_review',
        'generated_by',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'ai_review' => 'array',
            'viewed_at' => 'datetime',
        ];
    }

    public function userProgram(): BelongsTo
    {
        return $this->belongsTo(UserProgram::class);
    }
}
