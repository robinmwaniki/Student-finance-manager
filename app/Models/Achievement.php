<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Achievement extends Model
{
    protected $fillable = ['user_id', 'title', 'description', 'points', 'unlocked_at'];

    protected function casts(): array
    {
        return ['points' => 'integer', 'unlocked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
