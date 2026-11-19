<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class PublicInformationService
{
    public function dashboard(): array
    {
        $today = CarbonImmutable::now(config('app.timezone'));
        $year = (int) $today->format('Y');
        $holidays = collect($this->json('holidays:'.$year, "https://date.nager.at/api/v3/PublicHolidays/{$year}/KE", 86400))
            ->filter(fn ($item): bool => is_array($item) && isset($item['date'], $item['name']))
            ->map(fn (array $item): array => [
                'date' => (string) $item['date'],
                'name' => mb_substr(strip_tags((string) $item['name']), 0, 120),
                'local_name' => mb_substr(strip_tags((string) ($item['localName'] ?? $item['name'])), 0, 120),
            ])
            ->filter(fn (array $item): bool => $item['date'] >= $today->toDateString())
            ->sortBy('date')->take(8)->values()->all();

        $inflationPayload = $this->json(
            'kenya-inflation',
            'https://api.worldbank.org/v2/country/KEN/indicator/FP.CPI.TOTL.ZG?format=json&per_page=8',
            86400
        );
        $inflation = collect($inflationPayload[1] ?? [])
            ->filter(fn ($row): bool => isset($row['date']) && is_numeric($row['value']))
            ->map(fn (array $row): array => ['year' => (string) $row['date'], 'rate' => (float) $row['value']])
            ->values()->all();

        $cryptoPayload = $this->json(
            'crypto-prices',
            'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum&vs_currencies=kes,usd&include_24hr_change=true',
            300
        );
        $crypto = [];
        foreach (['bitcoin' => 'Bitcoin', 'ethereum' => 'Ethereum'] as $id => $name) {
            $item = $cryptoPayload[$id] ?? null;
            if (is_array($item) && is_numeric($item['kes'] ?? null) && is_numeric($item['usd'] ?? null)) {
                $crypto[] = [
                    'name' => $name,
                    'kes' => (float) $item['kes'],
                    'usd' => (float) $item['usd'],
                    'change' => is_numeric($item['kes_24h_change'] ?? null) ? (float) $item['kes_24h_change'] : null,
                ];
            }
        }

        $newsPayload = $this->json(
            'kenya-finance-news',
            'https://api.gdeltproject.org/api/v2/doc/doc?query=(Kenya%20economy%20OR%20Kenya%20inflation%20OR%20personal%20finance)&mode=artlist&format=json&sort=datedesc&maxrecords=6',
            900
        );
        $news = collect($newsPayload['articles'] ?? [])
            ->filter(fn ($item): bool => is_array($item) && filter_var($item['url'] ?? null, FILTER_VALIDATE_URL))
            ->map(fn (array $item): array => [
                'title' => mb_substr(strip_tags((string) ($item['title'] ?? 'Untitled article')), 0, 220),
                'url' => (string) $item['url'],
                'domain' => mb_substr((string) ($item['domain'] ?? parse_url($item['url'], PHP_URL_HOST) ?? ''), 0, 100),
                'published_at' => (string) ($item['seendate'] ?? ''),
            ])
            ->take(6)->values()->all();

        $market = $this->optionalMarketData();

        return [
            'inflation' => $inflation,
            'inflation_source' => 'World Bank · Consumer price inflation (annual %)',
            'holidays' => $holidays,
            'crypto' => $crypto,
            'news' => $news,
            'quote' => $this->dailyQuote($today),
            'market' => $market,
            'as_of' => $today->toIso8601String(),
        ];
    }

    public function weather(float $latitude, float $longitude): ?array
    {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        $key = 'weather:'.round($latitude, 2).':'.round($longitude, 2);
        $payload = $this->json(
            $key,
            'https://api.open-meteo.com/v1/forecast?latitude='.rawurlencode((string) $latitude)
                .'&longitude='.rawurlencode((string) $longitude)
                .'&current=temperature_2m,relative_humidity_2m,apparent_temperature,is_day,precipitation,weather_code,wind_speed_10m'
                .'&daily=temperature_2m_max,temperature_2m_min,precipitation_probability_max'
                .'&timezone=auto&forecast_days=3',
            900
        );

        if (! is_array($payload['current'] ?? null)) {
            return null;
        }

        return [
            'current' => $payload['current'],
            'daily' => $payload['daily'] ?? [],
            'timezone' => (string) ($payload['timezone'] ?? 'Local time'),
            'source' => 'Open-Meteo',
        ];
    }

    public function nearbyPlaces(float $latitude, float $longitude, string $type = 'bank'): array
    {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180
            || ! in_array($type, ['bank', 'atm'], true)) {
            return [];
        }

        $key = 'nearby:'.$type.':'.round($latitude, 3).':'.round($longitude, 3);
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $tag = $type === 'bank' ? 'bank' : 'atm';
        $query = '[out:json][timeout:8];(node["amenity"="'.$tag.'"](around:3000,'.$latitude.','.$longitude.');way["amenity"="'.$tag.'"](around:3000,'.$latitude.','.$longitude.'););out center 30;';
        try {
            $response = Http::asForm()->acceptJson()->timeout(10)->connectTimeout(3)
                ->post('https://overpass-api.de/api/interpreter', ['data' => $query]);
            if (! $response->successful() || ! is_array($response->json('elements'))) {
                return [];
            }

            $places = collect($response->json('elements'))
                ->filter(fn ($item): bool => is_array($item) && is_array($item['tags'] ?? null))
                ->map(function (array $item): array {
                    $tags = $item['tags'];
                    $lat = $item['lat'] ?? $item['center']['lat'] ?? null;
                    $lon = $item['lon'] ?? $item['center']['lon'] ?? null;

                    return [
                        'name' => mb_substr(strip_tags((string) ($tags['name'] ?? $tags['brand'] ?? 'Unnamed location')), 0, 120),
                        'operator' => mb_substr(strip_tags((string) ($tags['operator'] ?? $tags['brand'] ?? '')), 0, 100),
                        'latitude' => is_numeric($lat) ? (float) $lat : null,
                        'longitude' => is_numeric($lon) ? (float) $lon : null,
                        'type' => (string) ($tags['amenity'] ?? 'location'),
                    ];
                })
                ->filter(fn (array $place): bool => $place['latitude'] !== null && $place['longitude'] !== null)
                ->take(30)->values()->all();

            Cache::put($key, $places, now()->addMinutes(30));

            return $places;
        } catch (Throwable) {
            return [];
        }
    }

    public function geocode(string $place): ?array
    {
        $place = trim(mb_substr($place, 0, 120));
        if ($place === '') {
            return null;
        }

        $payload = $this->json(
            'geocode:'.sha1(mb_strtolower($place)),
            'https://geocoding-api.open-meteo.com/v1/search?name='.rawurlencode($place).'&count=1&language=en&format=json',
            86400
        );
        $item = $payload['results'][0] ?? null;
        if (! is_array($item) || ! is_numeric($item['latitude'] ?? null) || ! is_numeric($item['longitude'] ?? null)) {
            return null;
        }

        return [
            'name' => mb_substr(trim(($item['name'] ?? '').', '.($item['admin1'] ?? '').', '.($item['country'] ?? ''), ', '), 0, 160),
            'latitude' => (float) $item['latitude'],
            'longitude' => (float) $item['longitude'],
        ];
    }

    private function optionalMarketData(): array
    {
        $apiKey = config('information.market_api_key');
        if (! filled($apiKey)) {
            return ['configured' => false, 'stocks' => [], 'commodities' => []];
        }

        $stocks = [];
        foreach (['IBM' => 'IBM', 'MSFT' => 'Microsoft'] as $symbol => $label) {
            $payload = $this->json(
                'market-stock:'.$symbol,
                'https://www.alphavantage.co/query?function=GLOBAL_QUOTE&symbol='.$symbol.'&apikey='.rawurlencode($apiKey),
                900
            );
            $quote = $payload['Global Quote'] ?? [];
            if (is_numeric($quote['05. price'] ?? null)) {
                $stocks[] = [
                    'name' => $label,
                    'symbol' => $symbol,
                    'price' => (float) $quote['05. price'],
                    'currency' => 'USD',
                    'change_percent' => (string) ($quote['10. change percent'] ?? ''),
                ];
            }
        }

        $commodity = $this->json(
            'market-wti',
            'https://www.alphavantage.co/query?function=WTI&interval=monthly&apikey='.rawurlencode($apiKey),
            86400
        );
        $latest = collect($commodity['data'] ?? [])->first(fn ($row): bool => is_numeric($row['value'] ?? null));
        $commodities = $latest ? [[
            'name' => 'WTI crude oil',
            'price' => (float) $latest['value'],
            'unit' => 'USD / barrel',
            'date' => (string) ($latest['date'] ?? ''),
        ]] : [];

        return ['configured' => true, 'stocks' => $stocks, 'commodities' => $commodities];
    }

    private function dailyQuote(CarbonImmutable $date): array
    {
        $quotes = [
            ['text' => 'A budget is telling your money where to go instead of wondering where it went.', 'author' => 'John C. Maxwell'],
            ['text' => 'Do not save what is left after spending, but spend what is left after saving.', 'author' => 'Warren Buffett'],
            ['text' => 'Beware of little expenses; a small leak will sink a great ship.', 'author' => 'Benjamin Franklin'],
            ['text' => 'The art is not in making money, but in keeping it.', 'author' => 'Proverb'],
            ['text' => 'Financial peace is not the acquisition of stuff. It is learning to live on less than you make.', 'author' => 'Dave Ramsey'],
        ];
        $quote = $quotes[((int) $date->format('z')) % count($quotes)];

        return [...$quote, 'date' => $date->toDateString(), 'source' => 'Curated daily quote'];
    }

    private function json(string $key, string $url, int $ttl): array
    {
        $freshKey = 'public-info:fresh:'.$key;
        $staleKey = 'public-info:stale:'.$key;
        $fresh = Cache::get($freshKey);
        if (is_array($fresh)) {
            return $fresh;
        }

        try {
            $response = Http::acceptJson()->timeout(6)->connectTimeout(2)->get($url);
            if ($response->successful() && is_array($response->json())) {
                $payload = $response->json();
                Cache::put($freshKey, $payload, now()->addSeconds($ttl));
                Cache::put($staleKey, $payload, now()->addDays(7));

                return $payload;
            }
        } catch (Throwable) {
            // A public-data provider outage must never block the finance workspace.
        }

        $stale = Cache::get($staleKey);

        return is_array($stale) ? $stale : [];
    }
}
