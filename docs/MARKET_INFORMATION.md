# Kenya and market information widgets

## Provider and data handling

The market page loads each information section asynchronously. Provider outages do not block the authenticated finance application or the market page shell. Responses show the provider, data timestamp or observation year, and stale-cache state where applicable.

| Widget | Provider | Key | Cache / freshness |
| --- | --- | --- | --- |
| Kenya inflation history | World Bank indicator FP.CPI.TOTL.ZG | None | 1 day fresh; 7 days stale fallback |
| Kenya public holidays | Nager.Date | None | 7 days fresh; 30 days stale fallback |
| Cryptocurrency reference prices | CoinGecko simple price | None | 5 minutes fresh; 24 hours stale fallback |
| Stock watch | Alpha Vantage Global Quote | Optional ALPHA_VANTAGE_API_KEY | 6 hours fresh; 7 days stale fallback |
| Commodity references | Alpha Vantage WTI and Brent monthly series | Optional ALPHA_VANTAGE_API_KEY | 12 hours fresh; 14 days stale fallback |
| Kenya finance news | Google News RSS search | None | 30 minutes fresh; 24 hours stale fallback |
| Weather | Open-Meteo | None | 15 minutes fresh; 3 hours stale fallback |
| Nearby banks and ATMs | Nominatim + Overpass / OpenStreetMap | None | User-initiated request; not stored in application records |

Stock and commodity quotes are optional and disabled when no server-side Alpha Vantage key is configured. The application does not require external providers to load core finance pages.

## Location privacy

- The market and dashboard pages do not request geolocation on load.
- Browser geolocation is invoked only from an explicit “Use my location” button click, after which the browser asks the user for permission.
- A manual town, estate or landmark search is also available and is sent to Nominatim only after form submission.
- Coordinates are used for the immediate weather or nearby-place request and are not written to user records.
- Weather cache keys use coordinates rounded to two decimal places and expire after 15 minutes; nearby-place searches are not cached.
- Nearby search is limited to banks and ATMs within a 3 km radius. OpenStreetMap coverage may be incomplete or stale; confirm details with the provider.

## Inflation and purchasing power

The inflation chart uses annual Kenya consumer-price inflation observations from the World Bank. The purchasing-power calculator compounds the latest available annual inflation rate over the selected number of years. It is an illustration that holds the latest rate constant, not a forecast.

## Market and news disclaimers

- Crypto and stock prices are indicative provider data and can change quickly. Stock quotes may be delayed and markets may be closed.
- Commodity values are monthly reference series, not live trading quotes.
- Currency is shown as supplied by the provider (USD for Alpha Vantage commodities and stocks; USD and KES for crypto).
- News headlines and links are external publisher content. External text is stripped of markup and inserted into the page as text; links open separately with safe browsing attributes.
- None of these widgets is a source for financial transactions, investment advice or a guaranteed executable price.
- Weather data is model-based and may differ from local observations.

## Operations

Set ALPHA_VANTAGE_API_KEY in the server environment to enable stock and commodity widgets. Do not expose the key in Blade, JavaScript or browser requests. Clear the Laravel configuration cache after changing provider credentials.
