<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BankStatementCsvParser
{
    private const MAX_ROWS = 500;

    private const HEADER_ALIASES = [
        'date' => ['date', 'transaction date', 'value date', 'posting date', 'posted date', 'booking date'],
        'description' => ['description', 'details', 'narration', 'transaction details', 'particulars', 'reference', 'merchant', 'payee'],
        'type' => ['type', 'transaction type', 'direction', 'dr cr', 'debit credit'],
        'amount' => ['amount', 'transaction amount', 'value'],
        'debit' => ['debit', 'withdrawal', 'withdrawals', 'money out', 'paid out'],
        'credit' => ['credit', 'deposit', 'deposits', 'money in', 'paid in'],
        'category' => ['category', 'expense category'],
        'tag' => ['tag', 'tags', 'notes', 'note'],
    ];

    /**
     * Parse common bank statement layouts into the ledger import format.
     *
     * @return array<int, array{type:string,date:string,description:string,amount:string,category:string,tag:?string,submission_token:string}>
     */
    public function parse(string $path): array
    {
        $file = fopen($path, 'rb');
        if ($file === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded CSV could not be read.']);
        }

        try {
            $headers = fgetcsv($file, 8192, ',', '"', '');
            if (is_array($headers) === false || $headers === []) {
                throw ValidationException::withMessages(['file' => 'The CSV file is empty.']);
            }

            $columns = $this->mapHeaders($headers);
            if (isset($columns['date'], $columns['description']) === false
                || (isset($columns['amount']) === false && isset($columns['debit']) === false && isset($columns['credit']) === false)) {
                throw ValidationException::withMessages([
                    'file' => 'Could not recognise the statement columns. Include a date, description/details, and amount or debit/credit columns.',
                ]);
            }

            $rows = [];
            $line = 1;
            while (($values = fgetcsv($file, 8192, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null] || $values === []) {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    throw ValidationException::withMessages(['file' => 'A single import may contain at most 500 transactions.']);
                }

                $record = [];
                foreach ($columns as $field => $index) {
                    $record[$field] = trim((string) ($values[$index] ?? ''));
                }

                $date = $this->normaliseDate($record['date'] ?? '');
                $description = trim($record['description'] ?? '');
                if ($date === null || $date > now(config('app.timezone'))->toDateString()) {
                    throw ValidationException::withMessages(['file' => "Row {$line}: date must be valid and cannot be in the future. Supported formats include YYYY-MM-DD, DD/MM/YYYY and DD-MM-YYYY."]);
                }
                if ($description === '' || mb_strlen($description) > 100) {
                    throw ValidationException::withMessages(['file' => "Row {$line}: description/details are required and must not exceed 100 characters."]);
                }

                [$type, $amount] = $this->resolveTypeAndAmount($record, $line);
                $category = trim($record['category'] ?? '');
                if ($type === 'expense' && $category === '') {
                    $category = $this->suggestCategory($description);
                }
                if (mb_strlen($category) > 50) {
                    throw ValidationException::withMessages(['file' => "Row {$line}: category must not exceed 50 characters."]);
                }

                $rows[] = [
                    'type' => $type,
                    'date' => $date,
                    'description' => $description,
                    'amount' => $amount,
                    'category' => $category,
                    'tag' => ($record['tag'] ?? '') !== '' ? mb_substr($record['tag'], 0, 50) : null,
                    'submission_token' => (string) Str::uuid(),
                ];
            }
        } finally {
            fclose($file);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'The CSV contains a header but no transaction rows.']);
        }

        return $rows;
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, int>
     */
    private function mapHeaders(array $headers): array
    {
        $aliases = [];
        foreach (self::HEADER_ALIASES as $field => $names) {
            foreach ($names as $name) {
                $aliases[$this->normaliseHeader($name)] = $field;
            }
        }

        $columns = [];
        foreach ($headers as $index => $header) {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header) ?? (string) $header;
            $normalised = $this->normaliseHeader($header);
            if (isset($aliases[$normalised]) && !isset($columns[$aliases[$normalised]])) {
                $columns[$aliases[$normalised]] = $index;
            }
        }

        return $columns;
    }

    private function normaliseHeader(string $header): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($header)) ?? '');
    }

    private function normaliseDate(string $value): ?string
    {
        $value = trim($value);
        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y', '!d.m.Y', '!d/m/y', '!d-m-y', '!j M Y', '!j F Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $record
     * @return array{0:string,1:string}
     */
    private function resolveTypeAndAmount(array $record, int $line): array
    {
        $debit = $this->normaliseAmount($record['debit'] ?? '');
        $credit = $this->normaliseAmount($record['credit'] ?? '');
        $rawAmount = $record['amount'] ?? '';

        if (($record['debit'] ?? '') !== '' || ($record['credit'] ?? '') !== '') {
            if ($debit !== null && $debit > 0 && $credit !== null && $credit > 0) {
                throw ValidationException::withMessages(['file' => "Row {$line}: a row cannot contain both a debit and a credit amount."]);
            }
            if ($debit !== null && $debit > 0) {
                return ['expense', number_format($debit, 2, '.', '')];
            }
            if ($credit !== null && $credit > 0) {
                return ['income', number_format($credit, 2, '.', '')];
            }
            throw ValidationException::withMessages(['file' => "Row {$line}: debit or credit must contain a positive amount."]);
        }

        $amount = $this->normaliseAmount($rawAmount);
        if ($amount === null || $amount == 0) {
            throw ValidationException::withMessages(['file' => "Row {$line}: amount must be a non-zero numeric value."]);
        }

        $type = strtolower(trim($record['type'] ?? ''));
        $type = match ($type) {
            'income', 'credit', 'deposit', 'received', 'money in', 'cr' => 'income',
            'expense', 'debit', 'withdrawal', 'payment', 'paid', 'money out', 'dr' => 'expense',
            '' => $amount < 0 ? 'expense' : 'income',
            default => null,
        };
        if ($type === null) {
            throw ValidationException::withMessages(['file' => "Row {$line}: transaction type must identify income/credit or expense/debit."]);
        }

        return [$type, number_format(abs($amount), 2, '.', '')];
    }

    private function normaliseAmount(string $value): ?float
    {
        $value = trim(str_replace(',', '', preg_replace('/(?:KES|KSH)/i', '', $value) ?? $value));
        $value = str_replace(' ', '', $value);
        if ($value === '') {
            return null;
        }
        $negative = str_starts_with($value, '(') && str_ends_with($value, ')');
        if ($negative) {
            $value = substr($value, 1, -1);
        }
        if (preg_match('/^-?\d+(?:\.\d{1,2})?$/', $value) !== 1) {
            return null;
        }

        $amount = (float) $value;

        return $negative ? -abs($amount) : $amount;
    }

    private function suggestCategory(string $description): string
    {
        $text = strtolower($description);
        $rules = [
            'Food & Groceries' => ['supermarket', 'grocery', 'groceries', 'market', 'food', 'restaurant', 'cafe', 'hotel', 'butchery'],
            'Transport' => ['uber', 'bolt', 'matatu', 'fare', 'fuel', 'petrol', 'shell', 'rubis', 'transport', 'parking'],
            'Housing' => ['rent', 'landlord', 'caretaker', 'house'],
            'Utilities' => ['electricity', 'kplc', 'kenya power', 'water bill', 'nairobi water', 'internet', 'wifi'],
            'Airtime & Data' => ['airtime', 'safaricom', 'airtel', 'telkom', 'data bundle'],
            'Health' => ['pharmacy', 'hospital', 'clinic', 'medical', 'chemist'],
            'Education' => ['school', 'college', 'university', 'tuition', 'fees', 'books'],
            'Entertainment' => ['netflix', 'spotify', 'cinema', 'movie', 'game'],
        ];

        foreach ($rules as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $category;
                }
            }
        }

        return 'Other';
    }
}
