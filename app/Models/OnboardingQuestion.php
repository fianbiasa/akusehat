<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingQuestion extends Model
{
    protected $fillable = [
        'step',
        'category',
        'question_text',
        'input_type',
        'options',
        'validation_rules',
        'is_required',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'validation_rules' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function answers(): HasMany
    {
        return $this->hasMany(OnboardingAnswer::class, 'question_id');
    }
}
