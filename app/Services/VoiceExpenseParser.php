<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class VoiceExpenseParser
{
    public function parse(string $transcript): ?array
    {
        $text = trim(preg_replace('/\s+/', ' ', $transcript) ?? '');
        if ($text === '' || mb_strlen($text) > 2000) {
            return null;
        }

        if (preg_match('/(?:KES|KSH|SHS?|SHILLINGS?)?\s*([0-9][0-9,]*(?:\.\d{1,2})?)/i', $text, $amountMatch) !== 1) {
            return null;
        }

        $amount = (float) str_replace(',', '', $amountMatch[1]);
        if ($amount <= 0) {
            return null;
        }

        $date = CarbonImmutable::now(config('app.timezone'))->toDateString();
        if (preg_match('/\byesterday\b/i', $text) === 1) {
            $date = CarbonImmutable::now(config('app.timezone'))->subDay()->toDateString();
        } elseif (preg_match('/\b(today|now)\b/i', $text) !== 1) {
            $date = null;
        }

        $merchant = null;
        if (preg_match('/\b(?:on|at|for)\s+([a-z][a-z0-9 &-]{1,45})/i', $text, $merchantMatch) === 1) {
            $merchant = trim($merchantMatch[1]);
        }

        $category = match (true) {
            preg_match('/\b(food|lunch|dinner|breakfast|restaurant|groceries)\b/i', $text) === 1 => 'Food',
            preg_match('/\b(matatu|bus|fuel|taxi|uber|bolt|transport)\b/i', $text) === 1 => 'Transport',
            preg_match('/\b(rent|house|landlord)\b/i', $text) === 1 => 'Housing',
            default => 'General',
        };

        return [
            'amount' => round($amount, 2),
            'transaction_date' => $date,
            'merchant' => $merchant,
            'category' => $category,
            'tag' => 'Voice capture',
        ];
    }
}
