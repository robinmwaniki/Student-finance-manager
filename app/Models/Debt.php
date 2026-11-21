<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debt extends Model
{
    protected $fillable = [
        'user_id',
        'description',
        'amount',
        'type',
        'due_date',
        'status',
        'submission_token',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'lock_version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(DebtRepayment::class);
    }

    public function outstandingBalanceCents(): int
    {
        $toCents = static function (string $amount): int {
            [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

            return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
        };

        return max(
            0,
            $toCents((string) $this->amount) - $toCents((string) $this->repayments()->sum('amount')),
        );
    }
}
