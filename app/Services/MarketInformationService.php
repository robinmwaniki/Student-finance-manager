<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class MarketInformationService
{
    public function overview(): array
    {
        return [
            'inflation' => $this->inflation(),
            'holidays' => $this->holidays(),
            'crypto' => $this->crypto(),
            'stocks' => $this->stocks(),
            'commodities' => $this->commodities(),
            'news' => $this->news(),
            'quote' => $this->dailyQuote(),
        ];
    }

    public function inflation(): array
    {
        return $this->cached('kenya-inflation', 1440, 10080, function (): array {
            $response = Http::acceptJson()->timeout(6)->connectTimeout(3)->get(
                'https://api.worldbank.org/v2/country/KEN/indicator/FP.CPI.TOTL.ZG',
                ['format' => 'json', 'per_page' => 35],
            );
            $response->throw();
            $records = $response->json('1');
            if (! is_array($records)) {
                throw new \RuntimeException('Invalid World Bank response.');
            }

            $values = collect($records)
                ->filter(fn ($row): bool => isset($row['date']) && is_numeric($row['value']))
                ->map(fn ($row): array => ['year' => (int) $row['date'], 'inflation' => (float) $row['value']])
                ->sortBy('year')
                ->values()
                ->all();

            if ($values === []) {
                throw new \RuntimeException('No Kenya inflation observations returned.');
            }

            return [
                'available' => true,
                'country' => 'Kenya',
                'indicator' => 'Consumer price inflation (annual %)',
                'values' => $values,
                'latest' => $values[array_key_last($values)],
                'data_as_of' => (string) $values[array_key_last($values)]['year'],
                'source' => 'World Bank · FP.CPI.TOTL.ZG',
                'fetched_at' => now('UTC')->toIso8601String(),
            ];
        });
    }

    public function holidays(?int $year = null): array
    {
        $year ??= (int) now(config('app.timezone'))->format('Y');

        return $this->cached('kenya-holidays-'.$year, 10080, 43200, function () use ($year): array {
            $response = Http::acceptJson()->timeout(6)->connectTimeout(3)
                ->get('https://date.nager.at/api/v3/PublicHolidays/'.$year.'/KE');
            $response->throw();
            $rows = $response->json();

            if (! is_array($rows)) {
                throw new \RuntimeException('Invalid public holiday response.');
            }

            $holidays = collect($rows)->filter(fn ($row): bool => is_array($row)
                && isset($row['date'])
                && (isset($row['localName']) || isset($row['name'])))
                ->map(fn ($row): array => [
                    'date' => (string) $row['date'],
                    'name' => $this->safeText((string) ($row['localName'] ?? $row['name']), 120),
                    'english_name' => $this->safeText((string) ($row['name'] ?? ''), 120),
                    'types' => array_values(array_filter($row['types'] ?? [], 'is_string')),
                ])
                ->sortBy('date')
                ->values()
                ->all();

            return [
                'available' => true,
                'year' => $year,
                'holidays' => $holidays,
                'source' => 'Nager.Date public holiday API',
                'fetched_at' => now('UTC')->toIso8601String(),
            ];
        });
    }

    public function crypto(): array
    {
        return $this->cached('crypto-prices', 5, 1440, function (): array {
            $response = Http::acceptJson()->timeout(6)->connectTimeout(3)->get(
                'https://api.coingecko.com/api/v3/simple/price',
                [
                    'ids' => 'bitcoin,ethereum',
                    'vs_currencies' => 'usd,kes',
                    'include_24hr_change' => 'true',
                    'include_last_updated_at' => 'true',
                ],
            );
            $response->throw();
            $payload = $response->json();
            $quotes = [];

            foreach (['bitcoin' => 'Bitcoin', 'ethereum' => 'Ethereum'] as $id => $name) {
                $item = $payload[$id] ?? null;
                if (! is_array($item) || ! is_numeric($item['usd'] ?? null)) {
                    continue;
                }
                $quotes[] = [
                    'name' => $name,
                    'usd' => (float) $item['usd'],
                    'kes' => is_numeric($item['kes'] ?? null) ? (float) $item['kes'] : null,
                    'change_24h' => is_numeric($item['usd_24h_change'] ?? null) ? (float) $item['usd_24h_change'] : null,
                    'updated_at' => isset($item['last_updated_at']) ? CarbonImmutable::createFromTimestamp((int) $item['last_updated_at'], 'UTC')->toIso8601String() : null,
                ];
            }

            if ($quotes === []) {
                throw new \RuntimeException('No usable crypto quotes returned.');
            }

            return ['available' => true, 'quotes' => $quotes, 'source' => 'CoinGecko', 'fetched_at' => now('UTC')->toIso8601String(), 'delay_note' => 'Indicative provider quotes; prices can change quickly.'];
        });
    }

    public function stocks(): array
    {
        $key = config('services.alpha_vantage.key');
        if (! filled($key)) {
            return ['available' => false, 'message' => 'Optional stock quotes are disabled. Configure ALPHA_VANTAGE_API_KEY on the server.', 'source' => 'Alpha Vantage'];
        }

        return $this->cached('stock-quotes', 360, 10080, function () use ($key): array {
            $quotes = [];
            foreach (['AAPL', 'MSFT', 'NVDA'] as $symbol) {
                $response = Http::acceptJson()->timeout(6)->connectTimeout(3)->get('https://www.alphavantage.co/query', [
                    'function' => 'GLOBAL_QUOTE',
                    'symbol' => $symbol,
                    'apikey' => $key,
                ]);
                $response->throw();
                $row = $response->json('Global Quote');
                if (! is_array($row) || ! is_numeric($row['05. price'] ?? null)) {
                    continue;
                }
                $quotes[] = [
                    'symbol' => $symbol,
                    'price' => (float) $row['05. price'],
                    'change_percent' => is_string($row['10. change percent'] ?? null) ? $this->safeText($row['10. change percent'], 20) : null,
                    'trading_day' => $row['07. latest trading day'] ?? null,
                    'currency' => 'USD',
                ];
            }

            if ($quotes === []) {
                throw new \RuntimeException('No usable stock quotes returned.');
            }

            return ['available' => true, 'quotes' => $quotes, 'source' => 'Alpha Vantage Global Quote', 'fetched_at' => now('UTC')->toIso8601String(), 'delay_note' => 'Market data may be delayed and markets may be closed.'];
        });
    }

    public function commodities(): array
    {
        $key = config('services.alpha_vantage.key');
        if (! filled($key)) {
            return ['available' => false, 'message' => 'Optional commodity quotes are disabled. Configure ALPHA_VANTAGE_API_KEY on the server.', 'source' => 'Alpha Vantage'];
        }

        return $this->cached('commodity-quotes', 720, 20160, function () use ($key): array {
            $quotes = [];
            foreach (['WTI' => 'WTI crude oil', 'BRENT' => 'Brent crude oil'] as $symbol => $name) {
                $response = Http::acceptJson()->timeout(6)->connectTimeout(3)->get('https://www.alphavantage.co/query', [
                    'function' => $symbol,
                    'interval' => 'monthly',
                    'apikey' => $key,
                ]);
                $response->throw();
                $rows = $response->json('data');
                $latest = is_array($rows) ? ($rows[0] ?? null) : null;
                if (! is_array($latest) || ! is_numeric($latest['value'] ?? null)) {
                    continue;
                }
                $quotes[] = [
                    'name' => $name,
                    'price' => (float) $latest['value'],
                    'date' => $latest['date'] ?? null,
                    'currency' => 'USD',
                    'unit' => 'per barrel',
                ];
            }

            if ($quotes === []) {
                throw new \RuntimeException('No usable commodity prices returned.');
            }

            return ['available' => true, 'quotes' => $quotes, 'source' => 'Alpha Vantage commodity data', 'fetched_at' => now('UTC')->toIso8601String(), 'delay_note' => 'Monthly reference data, not a live trading quote.'];
        });
    }

    public function news(): array
    {
        return $this->cached('kenya-finance-news', 30, 1440, function (): array {
            $response = Http::withHeaders(['User-Agent' => 'StudentFinanceManager/1.0'])
                ->timeout(6)->connectTimeout(3)
                ->get('https://news.google.com/rss/search', [
                    'q' => 'Kenya finance economy',
                    'hl' => 'en-KE',
                    'gl' => 'KE',
                    'ceid' => 'KE:en',
                ]);
            $response->throw();
            $xml = simplexml_load_string($response->body(), \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            if ($xml === false) {
                throw new \RuntimeException('Invalid finance news feed.');
            }

            $items = [];
            foreach ($xml->channel->item ?? [] as $item) {
                $link = filter_var((string) $item->link, FILTER_VALIDATE_URL);
                if (! is_string($link) || ! in_array(parse_url($link, PHP_URL_SCHEME), ['https', 'http'], true)) {
                    continue;
                }
                $items[] = [
                    'title' => $this->safeText((string) $item->title, 180),
                    'publisher' => $this->safeText((string) ($item->source ?? 'News publisher'), 80),
                    'published_at' => $this->safeText((string) $item->pubDate, 80),
                    'url' => $link,
                ];
                if (count($items) >= 8) {
                    break;
                }
            }

            if ($items === []) {
                throw new \RuntimeException('No usable finance news items returned.');
            }

            return ['available' => true, 'items' => $items, 'source' => 'Google News RSS · Kenya finance and economy search', 'fetched_at' => now('UTC')->toIso8601String()];
        });
    }

    public function dailyQuote(): array
    {
        $quotes = [
            'A budget is a plan that gives every shilling a purpose.',
            'Small, consistent savings can build meaningful options over time.',
            'A clear record is the beginning of a better money decision.',
            'Spend with intention, save with patience, and review without judgement.',
            'A realistic financial plan is more useful than a perfect one you cannot follow.',
            'Know what is coming in, know what is going out, and decide what matters next.',
            'Progress is measured by the habits you can sustain, not by one perfect month.',
        ];
        $index = (int) now(config('app.timezone'))->format('z') % count($quotes);

        return ['text' => $quotes[$index], 'attribution' => 'Student Finance Manager · Daily reminder', 'date' => now(config('app.timezone'))->toDateString()];
    }

    public function weather(float $latitude, float $longitude): array
    {
        abort_if($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180, 422);
        $key = 'weather:'.round($latitude, 2).':'.round($longitude, 2);

        return $this->cached($key, 15, 180, function () use ($latitude, $longitude): array {
            $response = Http::acceptJson()->timeout(6)->connectTimeout(3)->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,weather_code,wind_speed_10m',
                'timezone' => 'auto',
            ]);
            $response->throw();
            $current = $response->json('current');
            if (! is_array($current) || ! is_numeric($current['temperature_2m'] ?? null)) {
                throw new \RuntimeException('Invalid weather response.');
            }

            return [
                'available' => true,
                'current' => $current,
                'timezone' => $response->json('timezone'),
                'source' => 'Open-Meteo',
                'fetched_at' => now('UTC')->toIso8601String(),
                'delay_note' => 'Current conditions are model-based and may differ from local observations.',
            ];
        });
    }

    public function geocode(string $location): ?array
    {
        $location = trim($location);
        if ($location === '' || mb_strlen($location) > 120) {
            return null;
        }

        $response = Http::withHeaders(['User-Agent' => 'StudentFinanceManager/1.0 (location lookup)'])
            ->acceptJson()->timeout(5)->connectTimeout(3)
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $location,
                'format' => 'jsonv2',
                'limit' => 1,
            ]);
        if (! $response->successful()) {
            return null;
        }
        $place = $response->json('0');
        if (! is_array($place) || ! is_numeric($place['lat'] ?? null) || ! is_numeric($place['lon'] ?? null)) {
            return null;
        }

        return [
            'latitude' => (float) $place['lat'],
            'longitude' => (float) $place['lon'],
            'label' => $this->safeText((string) ($place['display_name'] ?? $location), 160),
        ];
    }

    public function nearby(float $latitude, float $longitude): array
    {
        abort_if($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180, 422);
        $query = '[out:json][timeout:15];(node(around:3000,'.$latitude.','.$longitude.')[amenity~"^(bank|atm)$"];way(around:3000,'.$latitude.','.$longitude.')[amenity~"^(bank|atm)$"];);out center tags;';
        $response = Http::asForm()->acceptJson()->timeout(18)->connectTimeout(4)
            ->post('https://overpass-api.de/api/interpreter', ['data' => $query]);
        $response->throw();
        $elements = $response->json('elements');
        if (! is_array($elements)) {
            throw new \RuntimeException('Invalid OpenStreetMap response.');
        }

        $places = [];
        foreach ($elements as $element) {
            $tags = $element['tags'] ?? [];
            $lat = $element['lat'] ?? $element['center']['lat'] ?? null;
            $lon = $element['lon'] ?? $element['center']['lon'] ?? null;
            if (! is_array($tags) || ! is_numeric($lat) || ! is_numeric($lon)) {
                continue;
            }
            $distance = $this->distanceKm($latitude, $longitude, (float) $lat, (float) $lon);
            $places[] = [
                'name' => $this->safeText((string) ($tags['name'] ?? ($tags['amenity'] === 'atm' ? 'ATM' : 'Bank')), 100),
                'type' => ($tags['amenity'] ?? '') === 'atm' ? 'ATM' : 'Bank',
                'distance_km' => round($distance, 2),
                'address' => $this->safeText(implode(', ', array_filter([
                    $tags['addr:street'] ?? null,
                    $tags['addr:suburb'] ?? null,
                    $tags['addr:city'] ?? null,
                ])), 160),
                'latitude' => (float) $lat,
                'longitude' => (float) $lon,
            ];
        }

        usort($places, fn (array $a, array $b): int => $a['distance_km'] <=> $b['distance_km']);

        return [
            'available' => true,
            'places' => array_slice($places, 0, 20),
            'radius_km' => 3,
            'source' => 'OpenStreetMap contributors · Overpass API',
            'fetched_at' => now('UTC')->toIso8601String(),
            'disclaimer' => 'Map data may be incomplete or out of date. Confirm opening hours and services with the provider.',
        ];
    }

    public function purchasingPower(float $amount, int $years): ?array
    {
        $inflation = $this->inflation();
        if (! ($inflation['available'] ?? false) || ! is_numeric($inflation['latest']['inflation'] ?? null)) {
            return null;
        }
        $rate = (float) $inflation['latest']['inflation'];
        $futureCost = $amount * ((1 + ($rate / 100)) ** $years);

        return [
            'amount' => round($amount, 2),
            'years' => $years,
            'annual_inflation' => $rate,
            'estimated_future_cost' => round($futureCost, 2),
            'source_year' => $inflation['latest']['year'],
            'disclaimer' => 'Illustrative estimate using the latest annual Kenya inflation rate held constant; actual inflation will vary.',
        ];
    }

    private function cached(string $key, int $freshMinutes, int $staleMinutes, callable $fetch): array
    {
        $fresh = Cache::get('market:fresh:'.$key);
        if (is_array($fresh)) {
            return [...$fresh, 'stale' => false];
        }

        try {
            $data = $fetch();
            if (! is_array($data)) {
                throw new \RuntimeException('Invalid market data payload.');
            }
            Cache::put('market:fresh:'.$key, $data, now()->addMinutes($freshMinutes));
            Cache::put('market:stale:'.$key, $data, now()->addMinutes($staleMinutes));

            return [...$data, 'stale' => false];
        } catch (Throwable) {
            $stale = Cache::get('market:stale:'.$key);
            if (is_array($stale)) {
                return [...$stale, 'stale' => true];
            }

            return ['available' => false, 'stale' => false, 'message' => 'This information is temporarily unavailable. Please try again later.'];
        }
    }

    private function safeText(string $value, int $max): string
    {
        return mb_substr(trim(strip_tags($value)), 0, $max);
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
