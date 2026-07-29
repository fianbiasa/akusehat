<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbNutritionArticle extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'category',
        'content',
        'tags',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_published' => 'boolean',
        ];
    }
}
