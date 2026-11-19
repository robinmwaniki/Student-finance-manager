<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class MpesaSmsParser
{
    /**
     * Extract candidate details from supported outgoing M-PESA confirmations.
     * The result is untrusted until the account holder reviews it.
     *
     * @return array{amount: float, transaction_date: ?string, reference: ?string, merchant: ?string, category: string}|null
     */
    public function parse(string $message): ?array
    {
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');

        if ($message === '' || mb_strlen($message) > 3000) {
            return null;
        }

        if (preg_match('/\b(reversed|received|you have received|credited to your account|deposit|withdrawal|fuliza)\b/i', $message) === 1) {
            return null;
        }

        if (preg_match('/\b(sent to|paid to|buy goods(?: from| at)?|paybill|till(?: number)?|purchase)\b/i', $message) !== 1) {
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
        preg_match('/\b(?:sent to|paid to|buy goods(?: from| at)?|purchase from|paybill(?:\s+to)?|till(?:\s+number)?(?:\s+to)?)\s+(.+?)(?=\s+(?:\d{9,}|on\s+\d)|[.;]|$)/i', $message, $merchantMatch);

        $date = null;
        if (preg_match('/\bon\s+(\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4})/i', $message, $dateMatch) === 1) {
            foreach (['!d/m/y', '!d/m/Y', '!d-m-y', '!d-m-Y'] as $format) {
                try {
                    $parsed = CarbonImmutable::createFromFormat($format, $dateMatch[1], config('app.timezone'));
                    $errors = CarbonImmutable::getLastErrors();
                    if ($parsed !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                        $date = $parsed->toDateString();
                        break;
                    }
                } catch (\Throwable) {
                    // Unsupported or malformed dates remain blank for manual review.
                }
            }
        }

        if ($date !== null && CarbonImmutable::parse($date, config('app.timezone'))->isFuture()) {
            return null;
        }

        $merchant = isset($merchantMatch[1])
            ? trim(preg_replace('/\b\d{9,13}\b/', '', $merchantMatch[1]) ?? '', " .,-")
            : null;

        return [
            'amount' => round($amount, 2),
            'transaction_date' => $date,
            'reference' => isset($referenceMatch[1]) ? strtoupper($referenceMatch[1]) : null,
            'merchant' => $merchant !== '' ? mb_substr($merchant, 0, 150) : null,
            'category' => 'M-Pesa',
        ];
    }
}
