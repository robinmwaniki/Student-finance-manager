<?php

namespace App\Http\Controllers;

use App\Services\MpesaSmsParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransactionImportController extends Controller
{
    private const SESSION_PREFIX = 'finance_csv_import.';

    private const MAX_BYTES = 2097152;

    private const MAX_ROWS = 500;

    public function index(): View
    {
        return view('finance.import.index');
    }

    public function preview(Request $request, MpesaSmsParser $parser): RedirectResponse
    {
        $request->validate([
            'file' => ['nullable', 'required_without:mpesa_sms', 'file', 'mimes:csv,txt', 'max:2048'],
            'mpesa_sms' => ['nullable', 'required_without:file', 'string', 'max:100000'],
        ]);

        $rows = $request->filled('mpesa_sms')
            ? $parser->parseMany($request->string('mpesa_sms')->toString(), $request->user()->id)
            : $this->parseCsv($request->file('file')->getRealPath());

        $batch = (string) Str::uuid();
        $request->session()->put(self::SESSION_PREFIX.$batch, [
            'user_id' => $request->user()->id, 'rows' => $rows, 'created_at' => now()->timestamp,
        ]);

        return redirect()->route('imports.preview', ['batch' => $batch]);
    }

    private function parseCsv(string $path): array
    {
        $file = fopen($path, 'rb');
        if ($file === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded CSV could not be read.']);
        }
        try {
            $headers = fgetcsv($file, 4096, ',', '"', '');
            if (is_array($headers) === false) {
                throw ValidationException::withMessages(['file' => 'The CSV file is empty.']);
            }
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
            $headers = array_map(fn ($value) => strtolower(trim((string) $value)), $headers);
            if (array_diff(['type', 'date', 'description', 'amount'], $headers) !== []) {
                throw ValidationException::withMessages(['file' => 'Required columns: type, date, description, amount. Optional: category, tag.']);
            }
            $rows = [];
            $line = 1;
            while (($values = fgetcsv($file, 4096, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null] || $values === []) {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    throw ValidationException::withMessages(['file' => 'A single import may contain at most 500 transactions.']);
                }
                $record = [];
                foreach ($headers as $index => $header) {
                    if ($header !== '') {
                        $record[$header] = trim((string) ($values[$index] ?? ''));
                    }
                }
                $type = strtolower($record['type'] ?? '');
                $date = $record['date'] ?? '';
                $description = $record['description'] ?? '';
                $amount = $record['amount'] ?? '';
                $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                if (in_array($type, ['income', 'expense'], true) === false) {
                    throw ValidationException::withMessages(['file' => "Row {$line}: type must be income or expense."]);
                }
                if ($parsed === false || $parsed->format('Y-m-d') !== $date || $date > now(config('app.timezone'))->toDateString()) {
                    throw ValidationException::withMessages(['file' => "Row {$line}: date must be a valid YYYY-MM-DD date and cannot be in the future."]);
                }
                if ($description === '' || mb_strlen($description) > ($type === 'income' ? 100 : 50)) {
                    throw ValidationException::withMessages(['file' => "Row {$line}: description is required and must fit the selected transaction type."]);
                }
                if (preg_match('/^\\d{1,12}(?:\\.\\d{1,2})?$/', $amount) === 0 || (float) $amount <= 0) {
                    throw ValidationException::withMessages(['file' => "Row {$line}: amount must be positive with at most two decimal places."]);
                }
                $category = trim($record['category'] ?? '');
                $tag = trim($record['tag'] ?? '');
                if ($type === 'expense' && $category === '') {
                    $category = $description;
                }
                if (($type === 'expense' && mb_strlen($category) > 50) || mb_strlen($tag) > 50) {
                    throw ValidationException::withMessages(['file' => "Row {$line}: category and tag must not exceed 50 characters."]);
                }
                [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
                $whole = ltrim($whole, '0') ?: '0';
                $rows[] = ['type' => $type, 'date' => $date, 'description' => $description, 'amount' => $whole.'.'.str_pad($fraction, 2, '0'), 'category' => $category, 'tag' => $tag ?: null, 'submission_token' => (string) Str::uuid()];
            }
        } finally {
            fclose($file);
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'The CSV contains a header but no transaction rows.']);
        }

        return $rows;
    }

    public function show(Request $request, string $batch): View
    {
        $data = $request->session()->get(self::SESSION_PREFIX.$batch);
        abort_unless(is_array($data) && (int) ($data['user_id'] ?? 0) === $request->user()->id, 404);
        abort_if(now()->timestamp - (int) ($data['created_at'] ?? 0) > 1800, 410, 'This import preview has expired. Upload the source again.');
        $rows = $data['rows'] ?? [];

        return view('finance.import.preview', [
            'batch' => $batch, 'rows' => $rows,
            'incomeCount' => count(array_filter($rows, fn ($row) => $row['type'] === 'income')),
            'expenseCount' => count(array_filter($rows, fn ($row) => $row['type'] === 'expense')),
            'total' => array_sum(array_map(fn ($row) => (float) $row['amount'], $rows)),
        ]);
    }

    public function confirm(Request $request, string $batch): RedirectResponse
    {
        $data = $request->session()->get(self::SESSION_PREFIX.$batch);
        abort_unless(is_array($data) && (int) ($data['user_id'] ?? 0) === $request->user()->id, 404);
        abort_if(now()->timestamp - (int) ($data['created_at'] ?? 0) > 1800, 410, 'This import preview has expired.');
        $rows = $data['rows'] ?? [];
        abort_if($rows === [], 422, 'There are no transactions to import.');
        DB::transaction(function () use ($request, $rows): void {
            foreach ($rows as $row) {
                if ($row['type'] === 'income') {
                    $request->user()->income()->firstOrCreate(['submission_token' => $row['submission_token']], [
                        'source' => $row['description'], 'amount' => $row['amount'], 'transaction_date' => $row['date'], 'tag' => $row['tag'],
                    ]);
                } else {
                    $request->user()->expenses()->firstOrCreate(['submission_token' => $row['submission_token']], [
                        'category' => $row['category'], 'amount' => $row['amount'], 'transaction_date' => $row['date'], 'tag' => $row['tag'],
                        'original_amount' => $row['amount'], 'original_currency' => 'KES', 'exchange_rate' => 1,
                        'converted_amount' => $row['amount'], 'exchange_rate_updated_at' => now(), 'exchange_rate_source' => 'Statement import (KES)',
                    ]);
                }
            }
        });
        $request->session()->forget(self::SESSION_PREFIX.$batch);

        return redirect()->route('dashboard')->with('success', count($rows).' transactions imported. Re-imported M-PESA messages are deduplicated by receipt code.');
    }

    public function cancel(Request $request, string $batch): RedirectResponse
    {
        $data = $request->session()->get(self::SESSION_PREFIX.$batch);
        abort_unless(is_array($data) && (int) ($data['user_id'] ?? 0) === $request->user()->id, 404);
        $request->session()->forget(self::SESSION_PREFIX.$batch);

        return redirect()->route('imports.index')->with('success', 'Import preview discarded. No transactions were added.');
    }
}
