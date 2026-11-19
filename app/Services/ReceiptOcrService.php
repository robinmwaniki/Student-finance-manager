<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReceiptOcrService
{
    public function isConfigured(): bool
    {
        return config('ai.provider') === 'gemini' && filled(config('ai.api_key'));
    }

    public function extract(UploadedFile $receipt): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $model = config('ai.model', 'gemini-2.5-flash');
            $url = config('ai.base_url') ?: 'https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent';
            $response = Http::withHeaders(['x-goog-api-key' => config('ai.api_key')])
                ->acceptJson()
                ->timeout((int) config('ai.timeout', 8))
                ->connectTimeout((int) config('ai.connect_timeout', 3))
                ->post($url, [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [
                            ['text' => 'Read this receipt and return only a JSON object with merchant, date (YYYY-MM-DD or null), amount (number or null), and category. Use the final total, not subtotal or tax. Do not guess missing values.'],
                            ['inlineData' => [
                                'mimeType' => $receipt->getMimeType(),
                                'data' => base64_encode($receipt->getContent()),
                            ]],
                        ],
                    ]],
                    'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 256, 'responseMimeType' => 'application/json'],
                ]);

            $response->throw();
            $text = data_get($response->json(), 'candidates.0.content.parts.0.text');
            if (! is_string($text)) {
                return null;
            }

            $json = preg_replace('/^\s*\x60{3}(?:json)?|\x60{3}\s*$/i', '', trim($text));
            $data = json_decode($json, true);
            if (! is_array($data)) {
                return null;
            }

            $amount = is_numeric($data['amount'] ?? null) ? (float) $data['amount'] : null;
            $date = null;
            if (is_string($data['date'] ?? null) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $data['date']) === 1) {
                try {
                    $parsedDate = CarbonImmutable::createFromFormat('!Y-m-d', $data['date'], config('app.timezone'));
                    $dateErrors = CarbonImmutable::getLastErrors();
                    if ($parsedDate !== false && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
                        && ! $parsedDate->isFuture()) {
                        $date = $parsedDate->toDateString();
                    }
                } catch (\\Throwable) {
                    $date = null;
                }
            }

            return [
                'merchant' => is_string($data['merchant'] ?? null) ? mb_substr($data['merchant'], 0, 150) : null,
                'amount' => $amount !== null && $amount > 0 ? round($amount, 2) : null,
                'transaction_date' => $date,
                'category' => is_string($data['category'] ?? null) ? mb_substr($data['category'], 0, 50) : 'General',
                'tag' => 'Receipt OCR',
            ];
        } catch (Throwable $exception) {
            Log::warning('Receipt OCR request failed.', ['exception' => class_basename($exception)]);

            return null;
        }
    }
}
