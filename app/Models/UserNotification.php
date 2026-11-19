<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $table = 'user_notifications';

    public $timestamps = false;

    protected $fillable = ['user_id', 'message', 'status', 'type', 'created_at', 'deduplication_key', 'read_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
