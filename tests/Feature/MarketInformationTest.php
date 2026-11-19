<?php

namespace Tests\Feature;

use App\Services\MarketInformationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_page_loads_without_calling_external_providers_or_requesting_location(): void
    {
        Http::fake();
        $user = $this->makeUser();

        $this->actingAs($user)->get(route('market.index'))
            ->assertOk()
            ->assertSee('Use my location')
            ->assertSee('Your precise location is not requested on page load')
            ->assertSee('navigator.geolocation.getCurrentPosition');

        Http::assertNothingSent();
    }

    public function test_world_bank_inflation_data_is_validated_sorted_and_cached(): void
    {
        Http::fake([
            'api.worldbank.org/*' => Http::response([
                ['page' => 1],
                [
                    ['date' => '2024', 'value' => 4.5],
                    ['date' => '2022', 'value' => 7.6],
                    ['date' => '2023', 'value' => 7.7],
                    ['date' => '2025', 'value' => null],
                ],
            ], 200),
        ]);

        $service = app(MarketInformationService::class);
        $first = $service->inflation();
        $second = $service->inflation();

        $this->assertSame([2022, 2023, 2024], array_column($first['values'], 'year'));
        $this->assertSame(2024, $first['latest']['year']);
        $this->assertSame('World Bank · FP.CPI.TOTL.ZG', $first['source']);
        $this->assertFalse($first['stale']);
        $this->assertFalse($second['stale']);
        Http::assertSentCount(1);
    }

    public function test_inflation_uses_stale_cache_when_world_bank_is_unavailable(): void
    {
        Cache::put('market:stale:kenya-inflation', [
            'available' => true,
            'values' => [['year' => 2024, 'inflation' => 4.5]],
            'latest' => ['year' => 2024, 'inflation' => 4.5],
            'data_as_of' => '2024',
            'source' => 'World Bank',
        ], now()->addDays(7));
        Http::fake(['*' => Http::response([], 503)]);

        $result = app(MarketInformationService::class)->inflation();

        $this->assertTrue($result['stale']);
        $this->assertSame(4.5, $result['latest']['inflation']);
    }

    public function test_holiday_provider_response_is_normalized_and_cached(): void
    {
        Http::fake([
            'date.nager.at/*' => Http::response([
                ['date' => '2026-06-01', 'localName' => 'Madaraka Day', 'name' => 'Madaraka Day', 'types' => ['Public']],
                ['date' => '2026-10-20', 'localName' => 'Mashujaa Day', 'name' => 'Mashujaa Day', 'types' => ['Public']],
            ], 200),
        ]);

        $result = app(MarketInformationService::class)->holidays(2026);

        $this->assertTrue($result['available']);
        $this->assertCount(2, $result['holidays']);
        $this->assertSame('Madaraka Day', $result['holidays'][0]['name']);
        $this->assertSame('Nager.Date public holiday API', $result['source']);
    }

    public function test_crypto_provider_data_is_normalized_with_currency_and_timestamp(): void
    {
        Http::fake([
            'api.coingecko.com/*' => Http::response([
                'bitcoin' => ['usd' => 65000, 'kes' => 8450000, 'usd_24h_change' => 1.25, 'last_updated_at' => 1795046400],
                'ethereum' => ['usd' => 3200, 'kes' => 416000, 'usd_24h_change' => -0.5, 'last_updated_at' => 1795046400],
            ], 200),
        ]);

        $result = app(MarketInformationService::class)->crypto();

        $this->assertCount(2, $result['quotes']);
        $this->assertSame('Bitcoin', $result['quotes'][0]['name']);
        $this->assertSame(8450000.0, $result['quotes'][0]['kes']);
        $this->assertNotNull($result['quotes'][0]['updated_at']);
        $this->assertSame('CoinGecko', $result['source']);
    }

    public function test_optional_stock_and_commodity_provider_responses_are_normalized(): void
    {
        config(['services.alpha_vantage.key' => 'server-side-test-key']);
        Http::fake(function (ClientRequest $request) {
            if (str_contains($request->url(), 'GLOBAL_QUOTE')) {
                return Http::response([
                    'Global Quote' => [
                        '01. symbol' => 'AAPL',
                        '05. price' => '234.50',
                        '07. latest trading day' => '2026-11-18',
                        '10. change percent' => '1.25%',
                    ],
                ], 200);
            }

            return Http::response(['data' => [['date' => '2026-10-01', 'value' => '72.50']]], 200);
        });

        $market = app(MarketInformationService::class);
        $stocks = $market->stocks();
        $commodities = $market->commodities();

        $this->assertCount(3, $stocks['quotes']);
        $this->assertSame('AAPL', $stocks['quotes'][0]['symbol']);
        $this->assertSame(234.5, $stocks['quotes'][0]['price']);
        $this->assertCount(2, $commodities['quotes']);
        $this->assertSame(72.5, $commodities['quotes'][0]['price']);
        $this->assertSame('USD', $commodities['quotes'][0]['currency']);
    }

    public function test_expired_fresh_inflation_cache_is_refetched_from_provider(): void
    {
        Cache::put('market:fresh:kenya-inflation', [
            'available' => true,
            'values' => [['year' => 2023, 'inflation' => 7.7]],
            'latest' => ['year' => 2023, 'inflation' => 7.7],
            'data_as_of' => '2023',
            'source' => 'World Bank',
        ], now()->subMinute());
        Http::fake([
            'api.worldbank.org/*' => Http::response([
                ['page' => 1],
                [['date' => '2025', 'value' => 4.0]],
            ], 200),
        ]);

        $result = app(MarketInformationService::class)->inflation();

        $this->assertSame(2025, $result['latest']['year']);
        Http::assertSentCount(1);
    }

    public function test_world_bank_timeout_returns_a_graceful_unavailable_state(): void
    {
        Http::fake([
            'api.worldbank.org/*' => function () {
                throw new ConnectionException('simulated timeout');
            },
        ]);

        $result = app(MarketInformationService::class)->inflation();

        $this->assertFalse($result['available']);
        $this->assertStringContainsString('temporarily unavailable', $result['message']);
    }

    public function test_optional_stock_and_commodity_widgets_are_disabled_without_server_key(): void
    {
        Http::fake();
        config(['services.alpha_vantage.key' => null]);

        $this->assertFalse(app(MarketInformationService::class)->stocks()['available']);
        $this->assertFalse(app(MarketInformationService::class)->commodities()['available']);
        Http::assertNothingSent();
    }

    public function test_news_titles_are_stripped_of_markup_before_safe_dom_rendering(): void
    {
        Http::fake([
            'news.google.com/*' => Http::response('<?xml version="1.0"?><rss version="2.0"><channel><item><title><![CDATA[<script>alert(1)</script>Kenya finance update]]></title><link>https://example.test/story</link><pubDate>Thu, 19 Nov 2026 08:00:00 GMT</pubDate><source>Example publisher</source></item></channel></rss>', 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $result = app(MarketInformationService::class)->news();

        $this->assertCount(1, $result['items']);
        $this->assertStringNotContainsString('<script>', $result['items'][0]['title']);
        $this->assertSame('https://example.test/story', $result['items'][0]['url']);
        $this->assertStringContainsString('textContent = item.title', file_get_contents(resource_path('views/finance/market/index.blade.php')));
    }

    public function test_manual_nearby_search_geocodes_only_after_submit_and_returns_distance_sorted_places(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '-1.286389', 'lon' => '36.817223', 'display_name' => 'Nairobi, Kenya'],
            ], 200),
            'overpass-api.de/*' => Http::response([
                'elements' => [
                    ['type' => 'node', 'lat' => -1.29, 'lon' => 36.82, 'tags' => ['amenity' => 'atm', 'name' => 'ATM Westlands']],
                    ['type' => 'node', 'lat' => -1.31, 'lon' => 36.85, 'tags' => ['amenity' => 'bank', 'name' => 'Bank CBD']],
                ],
            ], 200),
        ]);
        $user = $this->makeUser();

        $this->actingAs($user)->postJson(route('market.nearby'), ['location' => 'Nairobi'])
            ->assertOk()
            ->assertJsonPath('searched_location', 'Nairobi, Kenya')
            ->assertJsonPath('places.0.name', 'ATM Westlands');

        Http::assertSentCount(2);
    }

    public function test_weather_endpoint_supports_manual_location_and_provider_failure_is_graceful(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '-1.286389', 'lon' => '36.817223', 'display_name' => 'Nairobi, Kenya'],
            ], 200),
            'api.open-meteo.com/*' => Http::response([
                'timezone' => 'Africa/Nairobi',
                'current' => [
                    'temperature_2m' => 24.5,
                    'relative_humidity_2m' => 55,
                    'apparent_temperature' => 25,
                    'precipitation' => 0,
                    'weather_code' => 1,
                    'wind_speed_10m' => 12,
                ],
            ], 200),
        ]);
        $user = $this->makeUser();

        $this->actingAs($user)->postJson(route('market.weather'), ['location' => 'Nairobi'])
            ->assertOk()
            ->assertJsonPath('current.temperature_2m', 24.5)
            ->assertJsonPath('source', 'Open-Meteo');

        Cache::forget('market:fresh:weather:-1.29:36.82');
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '-1.286389', 'lon' => '36.817223', 'display_name' => 'Nairobi, Kenya'],
            ], 200),
            'api.open-meteo.com/*' => Http::response([], 503),
        ]);
        $this->actingAs($user)->postJson(route('market.weather'), ['location' => 'Nairobi'])
            ->assertOk()
            ->assertJsonPath('stale', true);
    }

    public function test_purchasing_power_calculator_uses_latest_annual_inflation_observation(): void
    {
        Http::fake([
            'api.worldbank.org/*' => Http::response([
                ['page' => 1],
                [['date' => '2025', 'value' => 5.0]],
            ], 200),
        ]);
        $user = $this->makeUser();

        $this->actingAs($user)->postJson(route('market.purchasing-power'), [
            'amount' => 10000,
            'years' => 2,
        ])->assertOk()
            ->assertJsonPath('estimated_future_cost', 11025)
            ->assertJsonPath('annual_inflation', 5.0);
    }

    private function makeUser(): User
    {
        return User::create([
            'fullname' => 'Market Test User',
            'email' => 'market-'.Str::uuid().'@example.test',
            'password' => 'StrongPassword123',
        ]);
    }
}
