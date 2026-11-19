<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    protected $fillable = ['topic', 'question', 'options', 'correct_option', 'explanation', 'is_active'];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_active' => 'boolean'];
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'question_id');
    }
}
