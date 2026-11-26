<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class MpesaSmsParser
{
    /**
     * Parse one outgoing M-PESA message. Kept for the existing receipt-reading workflow.
     *
     * @return array{amount: float, transaction_date: ?string, reference: ?string, merchant: ?string, category: string}|null
     */
    public function parse(string $message): ?array
    {
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');
        if ($message === '' || mb_strlen($message) > 3000
            || preg_match('/\b(reversed|received|you have received|credited to your account|deposit|withdrawal|fuliza)\b/i', $message)
            || ! preg_match('/\b(sent to|paid to|buy goods(?: from| at)?|paybill|till(?: number)?|purchase)\b/i', $message)
            || !preg_match('/\b(?:Ksh|KES)\s*([0-9][0-9,]*(?:\.\d{1,2})?)/i', $message, $amountMatch)) {
            return null;
        }

        $amount = (float) str_replace(',', '', $amountMatch[1]);
        if ($amount <= 0 || $amount > 999999999999) {
            return null;
        }

        preg_match('/\b([A-Z0-9]{8,12})\s+Confirmed\b/i', $message, $referenceMatch);
        preg_match('/\b(?:sent to|paid to|buy goods(?: from| at)?|purchase from|paybill(?:\s+to)?|till(?:\s+number)?(?:\s+to)?)\s+(.+?)(?=\s+(?:\d{9,}|on\s+\d)|[.;]|$)/i', $message, $merchantMatch);

        $date = $this->extractDate($message);
        if ($date !== null && CarbonImmutable::parse($date, config('app.timezone'))->isFuture()) {
            return null;
        }

        $merchant = isset($merchantMatch[1])
            ? trim(preg_replace('/\b\d{9,13}\b/', '', $merchantMatch[1]) ?? '', ' .,-')
            : null;

        return [
            'amount' => round($amount, 2),
            'transaction_date' => $date,
            'reference' => isset($referenceMatch[1]) ? strtoupper($referenceMatch[1]) : null,
            'merchant' => $merchant !== '' ? mb_substr($merchant, 0, 150) : null,
            'category' => 'M-Pesa',
        ];
    }

    /**
     * Convert pasted confirmation SMS messages to the import preview's row format.
     *
     * @return array<int, array{type:string,date:string,description:string,amount:string,category:string,tag:string,submission_token:string}>
     */
    public function parseMany(string $messages, int|string $userId): array
    {
        $rows = [];
        $seen = [];

        foreach (preg_split('/\r\n|\r|\n/', trim($messages)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || mb_strlen($line) > 3000
                || !preg_match('/^([A-Z0-9]{8,12})\s+Confirmed\b/i', $line, $referenceMatch)) {
                continue;
            }

            $reference = strtoupper($referenceMatch[1]);
            if (isset($seen[$reference])) {
                continue;
            }

            if (!preg_match('/\b(?:Ksh|KES)\s*([0-9][0-9,]*(?:\.\d{1,2})?)/i', $line, $amountMatch)) continue;
            $amount = (float) str_replace(',', '', $amountMatch[1]);
            if ($amount <= 0 || $amount > 999999999999) {
                continue;
            }

            $date = $this->extractDate($line);
            if ($date === null || CarbonImmutable::parse($date, config('app.timezone'))->isFuture()) {
                continue;
            }

            $lower = strtolower($line);
            if (str_contains($lower, 'you have received') || str_contains($lower, 'credited to your account')) {
                $type = 'income';
                $description = 'M-PESA received · '.$reference;
                $category = '';
            } elseif (preg_match('/\b(sent to|paid to|buy goods(?: from| at)?|paybill|till(?: number)?|purchase)\b/i', $line)) {
                $candidate = $this->parse($line);
                if ($candidate === null) {
                    continue;
                }
                $type = 'expense';
                $description = 'M-PESA · '.$reference;
                $category = $candidate['category'];
            } else {
                continue;
            }

            $rows[] = [
                'type' => $type,
                'date' => $date,
                'description' => $description,
                'amount' => number_format($amount, 2, '.', ''),
                'category' => $category,
                'tag' => 'M-PESA '.$reference,
                'submission_token' => $this->stableToken((string) $userId, $reference),
            ];
            $seen[$reference] = true;

            if (count($rows) > 500) {
                throw ValidationException::withMessages(['mpesa_sms' => 'A single import may contain at most 500 transactions.']);
            }
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['mpesa_sms' => 'No supported successful M-PESA confirmation messages were found. Paste complete messages, one per line, including the receipt code, amount and date.']);
        }

        return $rows;
    }

    private function extractDate(string $message): ?string
    {
        if (!preg_match('/\bon\s+(\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4})/i', $message, $match)) return null;

        foreach (['!d/m/y', '!d/m/Y', '!d-m-y', '!d-m-Y'] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $match[1], config('app.timezone'));
                $errors = CarbonImmutable::getLastErrors();
                if ($parsed !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                    return $parsed->toDateString();
                }
            } catch (\Throwable) {
                // Unsupported dates are not imported.
            }
        }

        return null;
    }

    private function stableToken(string $userId, string $reference): string
    {
        $hex = substr(hash('sha256', $userId.'|mpesa|'.$reference), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 3) | 8);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }
}
