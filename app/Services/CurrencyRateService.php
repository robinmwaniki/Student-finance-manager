<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CurrencyRateService
{
    private const SOURCE = 'ExchangeRate-API (open.er-api.com)';

    public const CURRENCIES = [
        'AED', 'AUD', 'BWP', 'CAD', 'CHF', 'CNY', 'EGP', 'ETB', 'EUR', 'GBP',
        'GHS', 'INR', 'JPY', 'KES', 'NGN', 'QAR', 'RWF', 'SAR', 'TZS', 'UGX',
        'USD', 'ZAR',
    ];

    /**
     * Get a public exchange rate. Fresh data is cached for one hour; stale fallback expires after 24 hours.
     *
     * @return array{base: string, target: string, rate: float, source: string, as_of: string, stale: bool}|null
     */
    public function rate(string $base, string $target): ?array
    {
        $base = strtoupper($base);
        $target = strtoupper($target);

        if (! in_array($base, self::CURRENCIES, true) || ! in_array($target, self::CURRENCIES, true)) {
            return null;
        }

        if ($base === $target) {
            return [
                'base' => $base,
                'target' => $target,
                'rate' => 1.0,
                'source' => 'Identity conversion',
                'as_of' => now('UTC')->toIso8601String(),
                'stale' => false,
            ];
        }

        $snapshot = Cache::get('fx:latest:'.$base);
        $stale = false;

        if (! is_array($snapshot)) {
            try {
                $response = Http::acceptJson()
                    ->timeout(5)
                    ->connectTimeout(2)
                    ->get('https://open.er-api.com/v6/latest/'.$base);
                $response->throw();

                $payload = $response->json();
                if (($payload['result'] ?? null) !== 'success'
                    || strtoupper((string) ($payload['base_code'] ?? '')) !== $base
                    || ! is_array($payload['rates'] ?? null)) {
                    throw new \UnexpectedValueException('The exchange-rate provider returned an invalid rate table.');
                }

                $snapshot = [
                    'base' => $base,
                    'rates' => $payload['rates'],
                    'as_of' => isset($payload['time_last_update_utc'])
                        ? CarbonImmutable::parse($payload['time_last_update_utc'])->utc()->toIso8601String()
                        : now('UTC')->toIso8601String(),
                    'fetched_at' => now('UTC')->toIso8601String(),
                    'source' => self::SOURCE,
                ];

                Cache::put('fx:latest:'.$base, $snapshot, now()->addHour());
                Cache::put('fx:stale:'.$base, $snapshot, now()->addHours(24));
            } catch (Throwable $exception) {
                Log::warning('Public exchange-rate lookup failed.', ['exception' => class_basename($exception)]);
                $snapshot = Cache::get('fx:stale:'.$base);
                $stale = is_array($snapshot);
            }
        }

        $rate = is_array($snapshot) ? ($snapshot['rates'][$target] ?? null) : null;
        if (! is_numeric($rate) || (float) $rate <= 0) {
            return null;
        }

        return [
            'base' => $base,
            'target' => $target,
            'rate' => (float) $rate,
            'source' => (string) ($snapshot['source'] ?? self::SOURCE),
            'as_of' => (string) ($snapshot['as_of'] ?? now('UTC')->toIso8601String()),
            'stale' => $stale,
        ];
    }

    /**
     * Convert using a user-confirmed manual rate when the public provider has no usable quote.
     */
    public function convertWithRate(float $amount, float $rate): ?string
    {
        if (! is_finite($amount) || ! is_finite($rate) || $amount <= 0 || $rate <= 0
            || $amount > 999999999999 || $rate > 1000000) {
            return null;
        }

        $minorUnits = (int) round($amount * 100);
        $convertedMinorUnits = (int) round($minorUnits * $rate);

        return number_format($convertedMinorUnits / 100, 2, '.', '');
    }

    /**
     * Convert to the nearest minor unit; callers retain the returned rate and source metadata.
     *
     * @return array{amount: string, rate: float, source: string, as_of: string, stale: bool}|null
     */
    public function convert(float $amount, string $base, string $target): ?array
    {
        if (! is_finite($amount) || $amount <= 0 || $amount > 999999999999) {
            return null;
        }

        $quote = $this->rate($base, $target);
        if ($quote === null) {
            return null;
        }

        $minorUnits = (int) round($amount * 100);
        $convertedMinorUnits = (int) round($minorUnits * $quote['rate']);

        return [
            'amount' => number_format($convertedMinorUnits / 100, 2, '.', ''),
            'rate' => $quote['rate'],
            'source' => $quote['source'],
            'as_of' => $quote['as_of'],
            'stale' => $quote['stale'],
        ];
    }
}
