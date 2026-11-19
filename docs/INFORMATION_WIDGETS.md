# Kenya and Market Information Widgets

The authenticated **Market & Kenya** page provides optional public-information widgets. These widgets are read-only and never create or update income, expense, savings, debt, budget or other financial records.

## Included widgets and providers

| Widget | Provider | Cache / behaviour |
|---|---|---|
| Kenya inflation history | World Bank API, indicator `FP.CPI.TOTL.ZG` | 24 hours; annual observations |
| Kenya public holidays | Nager.Date, country `KE` | 24 hours; upcoming dates in the current year |
| Bitcoin and Ethereum reference prices | CoinGecko public API | 5 minutes |
| Finance and Kenya economy headlines | GDELT DOC API | 15 minutes; external publisher links |
| Daily money thought | Curated local quote list | Deterministic by local calendar date |
| Weather and short forecast | Open-Meteo | 15 minutes per rounded coordinate pair |
| Nearby banks and ATMs | OpenStreetMap Overpass | 30 minutes per rounded coordinate pair and type |
| Stock quotes and WTI crude oil | Alpha Vantage | 15 minutes for quotes; 24 hours for monthly commodity data |

Public data requests use short connection/request timeouts. Successful JSON responses are cached with a longer stale fallback window where applicable. Provider errors return an empty or cached result and must not prevent the page or core finance workflows from loading.

## Optional stock and commodity provider

Create an Alpha Vantage API key and configure it only on the server:

```dotenv
MARKET_DATA_API_KEY=your-server-side-key
```

Then refresh Laravel's configuration cache during deployment. The key is never rendered into HTML or sent to the browser. The widgets request IBM and Microsoft global quotes plus monthly WTI crude oil data. Alpha Vantage quotas and market-data delays apply; unavailable symbols or exhausted quotas are displayed as unavailable. Leave the variable empty to disable these optional calls.

## Location privacy

- The information page does not request geolocation on page load.
- Weather and nearby search expose explicit buttons that invoke the browser's location permission prompt only after a click.
- A manual place search is also available and uses Open-Meteo geocoding.
- Coordinates are sent to the authenticated application's server only for the requested weather or nearby lookup. Nearby lookup uses a 3 km OpenStreetMap query.
- Coordinates are rounded for cache keys. The application does not persist location history or create a location profile.
- Nearby results may be incomplete or outdated. Open the map result and verify the place before travelling.

## Data interpretation and disclaimers

- World Bank inflation data is annual CPI inflation, not a live monthly inflation rate. The purchasing-power calculator illustrates the effect of repeating the latest annual rate; it is not a forecast.
- Crypto, stock and commodity data may be delayed, cached or unavailable. No widget describes a price as real-time.
- News headlines are external content. The app displays escaped text and links to the original publisher.
- Public holiday lists may not include special government notices. Confirm dates with official Kenyan announcements.
- Weather forecasts are estimates. Verify conditions before making safety-critical decisions.
- Information widgets are educational/reference tools, not regulated financial, tax, legal or investment advice.

## Routes and rate limits

All routes require authentication:

- `GET /information` — public information dashboard
- `GET /information/weather` — weather by coordinates or manually geocoded place; 20 requests/minute
- `GET /information/nearby` — nearby bank/ATM search by coordinates or manually geocoded place; 10 requests/minute

The endpoints validate coordinate ranges and supported place types. Provider failures return a controlled unavailable response. External provider text is escaped by Blade or inserted using DOM `textContent`; it is never treated as executable HTML.
