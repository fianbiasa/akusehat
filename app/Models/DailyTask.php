<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyTask extends Model
{
    protected $fillable = [
        'user_program_id',
        'task_date',
        'task_type',
        'title',
        'description',
        'is_completed',
        'completed_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'task_date' => 'date:Y-m-d',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function userProgram(): BelongsTo
    {
        return $this->belongsTo(UserProgram::class);
    }
}
