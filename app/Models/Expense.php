<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [\n        'user_id', 'category', 'amount', 'original_amount', 'original_currency',\n        'exchange_rate', 'exchange_rate_source', 'exchange_rate_at', 'transaction_date', 'tag', 'submission_token',\n    ];

    protected function casts(): array
    {
        return [\n            'amount' => 'decimal:2',\n            'original_amount' => 'decimal:2',\n            'exchange_rate' => 'decimal:8',\n            'exchange_rate_at' => 'datetime',\n            'transaction_date' => 'date',\n        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
