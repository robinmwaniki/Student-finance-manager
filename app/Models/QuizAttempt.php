<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'question_id',
        'quiz_date',
        'selected_option',
        'is_correct',
        'points_awarded',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'quiz_date' => 'date',
            'is_correct' => 'boolean',
            'points_awarded' => 'integer',
            'answered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id');
    }
}
