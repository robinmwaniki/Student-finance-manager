<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class CurrencyRateService
{
    private const CURRENCIES = [
        'KES', 'USD', 'EUR', 'GBP', 'TZS', 'UGX', 'ZAR', 'AED', 'INR', 'CAD', 'AUD', 'JPY', 'CHF', 'CNY', 'NGN',
    ];

    public function currencies(): array
    {
        return self::CURRENCIES;
    }

    /**
     * @return array{from: string, to: string, rate: float, updated_at: string, source: string, stale: bool}|null
     */
    public function quote(string $from, string $to): ?array
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if (! in_array($from, self::CURRENCIES, true) || ! in_array($to, self::CURRENCIES, true)) {
            return null;
        }

        if ($from === $to) {
            return [
                'from' => $from,
                'to' => $to,
                'rate' => 1.0,
                'updated_at' => now(config('app.timezone'))->toIso8601String(),
                'source' => 'Same currency',
                'stale' => false,
            ];
        }

        $rates = $this->ratesFor($from);
        $rate = $rates['rates'][$to] ?? null;

        if (! is_numeric($rate) || (float) $rate <= 0) {
            return null;
        }

        return [
            'from' => $from,
            'to' => $to,
            'rate' => (float) $rate,
            'updated_at' => $rates['updated_at'],
            'source' => 'Open Exchange Rates API (open.er-api.com)',
            'stale' => $rates['stale'],
        ];
    }

    private function ratesFor(string $base): ?array
    {
        $freshKey = 'public-fx:fresh:'.$base;
        $staleKey = 'public-fx:stale:'.$base;
        $fresh = Cache::get($freshKey);

        if (is_array($fresh)) {
            return [...$fresh, 'stale' => false];
        }

        try {
            $response = Http::acceptJson()
                ->timeout(5)
                ->connectTimeout(2)
                ->get('https://open.er-api.com/v6/latest/'.rawurlencode($base));

            if ($response->successful()
                && $response->json('result') === 'success'
                && is_array($response->json('rates'))) {
                $data = [
                    'rates' => $response->json('rates'),
                    'updated_at' => (string) ($response->json('time_last_update_utc') ?? now('UTC')->toIso8601String()),
                ];
                Cache::put($freshKey, $data, now()->addHour());
                Cache::put($staleKey, $data, now()->addDays(7));

                return [...$data, 'stale' => false];
            }
        } catch (Throwable) {
            // Fall through to the last known public rate, if one exists.
        }

        $stale = Cache::get($staleKey);

        return is_array($stale) ? [...$stale, 'stale' => true] : null;
    }
}
