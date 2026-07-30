<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    protected $fillable = [
        'user_program_id',
        'item_date',
        'label',
        'is_checked',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'item_date' => 'date:Y-m-d',
            'is_checked' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    public function userProgram(): BelongsTo
    {
        return $this->belongsTo(UserProgram::class);
    }
}
