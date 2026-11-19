<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicInformationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['information.market_api_key' => null]);
    }

    public function test_information_page_renders_public_data_and_provider_sources(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'date.nager.at')) {
                return Http::response([['date' => '2026-12-12', 'name' => 'Jamhuri Day', 'localName' => 'Jamhuri Day']], 200);
            }
            if (str_contains($request->url(), 'api.worldbank.org')) {
                return Http::response([['page' => 1], [['date' => '2025', 'value' => 4.5], ['date' => '2024', 'value' => 6.2]]], 200);
            }
            if (str_contains($request->url(), 'coingecko.com')) {
                return Http::response([
                    'bitcoin' => ['kes' => 9000000, 'usd' => 65000, 'kes_24h_change' => 1.5],
                    'ethereum' => ['kes' => 300000, 'usd' => 2200, 'kes_24h_change' => -0.5],
                ], 200);
            }
            if (str_contains($request->url(), 'gdeltproject.org')) {
                return Http::response(['articles' => [[
                    'title' => 'Kenya economy update',
                    'url' => 'https://news.example.test/kenya-economy',
                    'domain' => 'news.example.test',
                    'seendate' => '20261119T100000Z',
                ]]], 200);
            }

            return Http::response([], 503);
        });

        $this->actingAs($this->makeUser())
            ->get(route('information.index'))
            ->assertOk()
            ->assertSee('Kenya inflation tracker')
            ->assertSee('4.5%')
            ->assertSee('Jamhuri Day')
            ->assertSee('Bitcoin')
            ->assertSee('Kenya economy update')
            ->assertSee('Market data provider not configured')
            ->assertSee('Use my location')
            ->assertSee('Search area');

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'open-meteo.com/v1/forecast')
            || str_contains($request->url(), 'overpass-api.de'));
    }

    public function test_provider_failures_do_not_break_the_information_page(): void
    {
        Http::fake(['*' => Http::response(['message' => 'provider unavailable'], 503)]);

        $this->actingAs($this->makeUser())
            ->get(route('information.index'))
            ->assertOk()
            ->assertSee('Kenya inflation tracker')
            ->assertSee('Inflation data is temporarily unavailable')
            ->assertSee('Holiday information is currently unavailable')
            ->assertSee('Crypto market data is temporarily unavailable')
            ->assertSee('News headlines are temporarily unavailable');
    }

    public function test_weather_requires_an_explicit_location_and_returns_provider_data(): void
    {
        Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response([
            'current' => [
                'temperature_2m' => 24,
                'apparent_temperature' => 25,
                'relative_humidity_2m' => 65,
                'wind_speed_10m' => 8,
            ],
            'daily' => [
                'time' => ['2026-11-19'],
                'temperature_2m_min' => [18],
                'temperature_2m_max' => [27],
            ],
            'timezone' => 'Africa/Nairobi',
        ], 200)]);

        $this->actingAs($this->makeUser())
            ->getJson(route('information.weather', ['latitude' => -1.2864, 'longitude' => 36.8172]))
            ->assertOk()
            ->assertJsonPath('current.temperature_2m', 24)
            ->assertJsonPath('source', 'Open-Meteo');

        $this->getJson(route('information.weather', ['latitude' => 120, 'longitude' => 36]))
            ->assertUnprocessable();
    }

    public function test_manual_nearby_search_geocodes_only_after_submission_and_uses_openstreetmap(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'geocoding-api.open-meteo.com')) {
                return Http::response(['results' => [[
                    'name' => 'Westlands',
                    'admin1' => 'Nairobi',
                    'country' => 'Kenya',
                    'latitude' => -1.2676,
                    'longitude' => 36.8108,
                ]]], 200);
            }
            if (str_contains($request->url(), 'overpass-api.de')) {
                return Http::response(['elements' => [[
                    'type' => 'node',
                    'lat' => -1.268,
                    'lon' => 36.811,
                    'tags' => ['amenity' => 'bank', 'name' => 'Example Bank'],
                ]]], 200);
            }

            return Http::response([], 503);
        });

        $this->actingAs($this->makeUser())
            ->getJson(route('information.nearby', ['type' => 'bank', 'place' => 'Westlands, Nairobi']))
            ->assertOk()
            ->assertJsonPath('location.name', 'Westlands, Nairobi, Kenya')
            ->assertJsonPath('places.0.name', 'Example Bank')
            ->assertJsonPath('source', 'OpenStreetMap contributors · results may be incomplete');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'overpass-api.de')
            && str_contains((string) ($request->data()['data'] ?? ''), 'around:3000'));
    }

    public function test_nearby_search_validates_type_and_coordinates(): void
    {
        $this->actingAs($this->makeUser())
            ->getJson(route('information.nearby', ['type' => 'cash', 'latitude' => 500, 'longitude' => 0]))
            ->assertUnprocessable();
    }

    public function test_market_api_key_is_never_required_for_core_widgets_or_exposed_to_the_page(): void
    {
        Http::fake(['*' => Http::response([], 503)]);

        $this->actingAs($this->makeUser())
            ->get(route('information.index'))
            ->assertOk()
            ->assertSee('MARKET_DATA_API_KEY')
            ->assertDontSee('AlphaVantageSecret');

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'alphavantage.co'));
    }

    private function makeUser(): User
    {
        return User::create([
            'fullname' => 'Information User',
            'email' => 'information@example.test',
            'password' => 'password',
        ]);
    }
}
