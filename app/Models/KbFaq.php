<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbFaq extends Model
{
    protected $fillable = [
        'question',
        'answer',
        'category',
        'order',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }
}
