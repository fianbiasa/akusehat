<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiPromptTemplate extends Model
{
    protected $fillable = [
        'key',
        'purpose',
        'template',
        'variables',
        'response_schema',
        'version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'response_schema' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
