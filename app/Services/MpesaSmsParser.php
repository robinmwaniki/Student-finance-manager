<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class MpesaSmsParser
{
    /**
     * Parse supported outgoing M-Pesa confirmation messages.
     *
     * @return array{amount: float, transaction_date: ?string, reference: ?string, merchant: ?string, category: string}|null
     */
    public function parse(string $message): ?array
    {
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');

        if ($message === '' || mb_strlen($message) > 3000) {
            return null;
        }

        if (preg_match('/\b(received|you have received|credited to your account)\b/i', $message) === 1) {
            return null;
        }

        if (preg_match('/\b(sent to|paid to|buy goods from|paybill|till number|purchase)\b/i', $message) !== 1) {
            return null;
        }

        if (preg_match('/\b(?:Ksh|KES)\s*([0-9][0-9,]*(?:\.\d{1,2})?)/i', $message, $amountMatch) !== 1) {
            return null;
        }

        $amount = (float) str_replace(',', '', $amountMatch[1]);
        if ($amount <= 0) {
            return null;
        }

        preg_match('/\b([A-Z0-9]{8,12})\s+Confirmed\b/i', $message, $referenceMatch);
        preg_match('/\b(?:sent to|paid to|buy goods from|purchase from)\s+(.+?)(?=\s+(?:\d{9,}|on\s+\d)|$)/i', $message, $merchantMatch);

        $date = null;
        if (preg_match('/\bon\s+(\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4})/i', $message, $dateMatch) === 1) {
            foreach (['!d/m/y', '!d/m/Y', '!d-m-y', '!d-m-Y'] as $format) {
                $parsed = CarbonImmutable::createFromFormat($format, $dateMatch[1], config('app.timezone'));
                if ($parsed !== false) {
                    $date = $parsed->toDateString();
                    break;
                }
            }
        }

        return [
            'amount' => round($amount, 2),
            'transaction_date' => $date,
            'reference' => $referenceMatch[1] ?? null,
            'merchant' => isset($merchantMatch[1]) ? trim($merchantMatch[1], " .,-") : null,
            'category' => 'M-Pesa',
        ];
    }
}
