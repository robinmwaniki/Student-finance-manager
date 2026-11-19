<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseCaptureWebhookCredential extends Model
{
    protected $fillable = [
        'user_id', 'token_hash', 'previous_token_hash', 'signing_secret',
        'previous_signing_secret', 'previous_expires_at', 'rotated_at',
    ];

    protected function casts(): array
    {
        return [
            'signing_secret' => 'encrypted',
            'previous_signing_secret' => 'encrypted',
            'previous_expires_at' => 'datetime',
            'rotated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
