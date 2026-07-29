<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RuleEngineRule extends Model
{
    protected $fillable = [
        'category',
        'name',
        'condition',
        'action',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'condition' => 'array',
            'action' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
