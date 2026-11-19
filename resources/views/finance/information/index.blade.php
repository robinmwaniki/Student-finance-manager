@extends('layouts.app')
@section('title', 'Kenya & market information')
@section('heading', 'Kenya & market information')
@section('content')
<style>
.info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.info-card{min-width:0}.info-card h2{margin:0 0 8px;font:600 16px 'Space Grotesk',sans-serif}.info-muted{color:#8793a6;font-size:11px;line-height:1.6}.info-source{margin-top:12px;padding-top:9px;border-top:1px solid #edf0f5;color:#96a0b0;font-size:10px}.info-list{list-style:none;margin:0;padding:0}.info-list li{padding:10px 0;border-bottom:1px solid #f0f2f6;font-size:12px}.info-list li:last-child{border:0}.info-row{display:flex;justify-content:space-between;gap:12px;align-items:center}.info-tag{font-size:10px;color:#64748b;background:#f1f4f9;padding:4px 7px;border-radius:20px}.inflation-bars{display:grid;gap:8px;margin-top:12px}.inflation-row{display:grid;grid-template-columns:42px 1fr 48px;gap:8px;align-items:center;font-size:11px}.inflation-track{height:9px;border-radius:10px;background:#edf1f6;overflow:hidden}.inflation-fill{height:100%;border-radius:10px;background:#607da9}.info-field{height:39px;border:1px solid #dfe5ee;border-radius:8px;padding:0 10px;font:12px 'DM Sans',sans-serif;min-width:0}.info-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.info-result{margin-top:13px;padding:12px;border-radius:9px;background:#f7f9fc;font-size:12px;line-height:1.6}.info-result:empty{display:none}.info-result a{color:#344a75}.info-note{font-size:10px;color:#8490a3;line-height:1.6}.info-stat{font:600 23px 'Space Grotesk',sans-serif;margin:7px 0}.info-link{color:#344a75;text-decoration:none}.info-link:hover{text-decoration:underline}
@media(max-width:760px){.info-grid{grid-template-columns:1fr}}
</style>

<div class="info-grid">
    <section class="card info-card">
        <h2>Kenya inflation tracker</h2>
        <p class="info-muted">Annual consumer price inflation from the World Bank. Historical values are annual observations, not a live monthly CPI reading.</p>
        @if(count($inflation))
            @php($maxInflation = max(1, max(array_map(fn ($item) => abs($item['rate']), $inflation))))
            <div class="inflation-bars" aria-label="Annual Kenya inflation chart">
                @foreach(array_reverse($inflation) as $item)
                    <div class="inflation-row"><span>{{ $item['year'] }}</span><div class="inflation-track"><div class="inflation-fill" style="width:{{ min(100, max(0, abs($item['rate']) / $maxInflation * 100)) }}%"></div></div><strong>{{ number_format($item['rate'],1) }}%</strong></div>
                @endforeach
            </div>
            <div style="border-top:1px solid #edf0f5;margin-top:15px;padding-top:13px">
                <div class="info-muted">Purchasing-power illustration using the latest annual rate ({{ number_format(end($inflation)['rate'],2) }}%).</div>
                <div class="info-actions"><input class="info-field" id="purchasingAmount" type="number" min="0" step="0.01" value="10000" aria-label="Amount in Kenya shillings"><select class="info-field" id="purchasingYears" aria-label="Years" style="max-width:100px"><option value="1">1 year</option><option value="3">3 years</option><option value="5">5 years</option><option value="10">10 years</option></select><button class="btn btn-small" id="calculatePurchasing" type="button">Estimate</button></div>
                <div id="purchasingResult" class="info-result" aria-live="polite">Enter an amount to estimate how its purchasing power changes over time.</div>
            </div>
        @else
            <p class="info-result">Inflation data is temporarily unavailable. Try again later.</p>
        @endif
        <div class="info-source">{{ $inflation_source }} · World Bank API · Annual data</div>
    </section>

    <section class="card info-card">
        <h2>Kenya public holidays</h2>
        <p class="info-muted">Upcoming national public holidays for {{ now(config('app.timezone'))->format('Y') }}. Confirm official notices for special or newly declared holidays.</p>
        @forelse($holidays as $holiday)
            <div class="info-row" style="padding:10px 0;border-bottom:1px solid #f0f2f6"><div><strong style="font-size:12px">{{ $holiday['name'] }}</strong><div class="info-muted">{{ $holiday['local_name'] }}</div></div><span class="info-tag">{{ \Carbon\CarbonImmutable::parse($holiday['date'])->format('D, d M') }}</span></div>
        @empty
            <p class="info-result">Holiday information is currently unavailable.</p>
        @endforelse
        <div class="info-source">Nager.Date public holidays API · Kenya (KE)</div>
    </section>

    <section class="card info-card">
        <h2>Digital assets</h2>
        <p class="info-muted">Indicative market prices. Crypto prices can change rapidly and are not investment recommendations.</p>
        @forelse($crypto as $coin)
            <div class="info-row" style="padding:12px 0;border-bottom:1px solid #f0f2f6"><div><strong style="font-size:12px">{{ $coin['name'] }}</strong><div class="info-muted">USD {{ number_format($coin['usd'],2) }}</div></div><div style="text-align:right"><strong style="font-size:13px">KES {{ number_format($coin['kes'],2) }}</strong><div class="info-muted" style="color:{{ ($coin['change'] ?? 0) >= 0 ? '#16835b' : '#b42318' }}">{{ $coin['change'] === null ? '24h change unavailable' : number_format($coin['change'],2).'%' }}</div></div></div>
        @empty
            <p class="info-result">Crypto market data is temporarily unavailable.</p>
        @endforelse
        <div class="info-source">CoinGecko public API · Cached market data · Delays may apply</div>
    </section>

    <section class="card info-card">
        <h2>Stocks & commodities</h2>
        @if($market['configured'])
            @forelse($market['stocks'] as $stock)
                <div class="info-row" style="padding:9px 0;border-bottom:1px solid #f0f2f6"><span><strong style="font-size:12px">{{ $stock['name'] }}</strong><span class="info-muted"> · {{ $stock['symbol'] }}</span></span><strong style="font-size:12px">{{ $stock['currency'] }} {{ number_format($stock['price'],2) }}</strong></div>
            @empty
                <p class="info-muted">Stock quotes are unavailable or the provider quota has been reached.</p>
            @endforelse
            @forelse($market['commodities'] as $commodity)
                <div class="info-row" style="padding:9px 0"><span style="font-size:12px">{{ $commodity['name'] }}</span><strong style="font-size:12px">{{ number_format($commodity['price'],2) }} {{ $commodity['unit'] }}</strong></div>
            @empty
                <p class="info-muted">Commodity data is currently unavailable.</p>
            @endforelse
            <div class="info-source">Alpha Vantage · Check each provider's market-data delay and usage limits</div>
        @else
            <p class="info-muted">Stock and commodity quotes are optional. Add a server-side <code>MARKET_DATA_API_KEY</code> from Alpha Vantage to enable these widgets. No key is sent to the browser.</p>
            <div class="info-result">Market data provider not configured. Other information widgets work without it.</div>
            <div class="info-source">Alpha Vantage · Optional provider</div>
        @endif
    </section>

    <section class="card info-card">
        <h2>Daily money thought</h2>
        <blockquote style="margin:12px 0;font:500 17px 'Space Grotesk',sans-serif;line-height:1.5;color:#273957">“{{ $quote['text'] }}”</blockquote>
        <div class="info-muted">— {{ $quote['author'] }}</div>
        <div class="info-source">{{ $quote['source'] }} · {{ $quote['date'] }}</div>
    </section>

    <section class="card info-card">
        <h2>Kenya finance news</h2>
        <p class="info-muted">Recent headlines from the GDELT news index. Open articles on their original publisher's website.</p>
        @forelse($news as $article)
            <div style="padding:10px 0;border-bottom:1px solid #f0f2f6"><a class="info-link" style="font-size:12px;font-weight:700" href="{{ $article['url'] }}" target="_blank" rel="noopener noreferrer">{{ $article['title'] }}</a><div class="info-muted">{{ $article['domain'] }} @if($article['published_at']) · {{ $article['published_at'] }} @endif</div></div>
        @empty
            <p class="info-result">News headlines are temporarily unavailable.</p>
        @endforelse
        <div class="info-source">GDELT Project · External headlines are not financial advice</div>
    </section>

    <section class="card info-card">
        <h2>Local weather</h2>
        <p class="info-muted">Weather is requested only after you choose a location. You can share your device location or enter a town/area manually.</p>
        <div class="info-actions"><button class="btn btn-small" id="weatherUseLocation" type="button">Use my location</button><input class="info-field" id="weatherPlace" type="text" maxlength="120" placeholder="e.g. Nairobi, Kenya" aria-label="Weather location"><button class="btn btn-light btn-small" id="weatherSearch" type="button">Search</button></div>
        <div id="weatherResult" class="info-result" role="status" aria-live="polite"></div>
        <div class="info-source">Open-Meteo · Forecast is indicative and may change</div>
    </section>

    <section class="card info-card">
        <h2>Nearby banks & ATMs</h2>
        <p class="info-muted">Location is not accessed until you press a button. Search manually by town or use your device location. OpenStreetMap coverage may be incomplete.</p>
        <div class="info-actions"><select class="info-field" id="nearbyType" aria-label="Place type"><option value="bank">Banks</option><option value="atm">ATMs</option></select><button class="btn btn-small" id="nearbyUseLocation" type="button">Use my location</button></div>
        <form id="nearbySearchForm" class="info-actions"><input class="info-field" id="nearbyPlace" type="text" maxlength="120" placeholder="e.g. Westlands, Nairobi" aria-label="Search area" required><button class="btn btn-light btn-small" type="submit">Search area</button></form>
        <div id="nearbyResult" class="info-result" role="status" aria-live="polite"></div>
        <div class="info-source">OpenStreetMap contributors · Approximate search radius: 3 km</div>
    </section>
</div>

<p class="info-note" style="margin-top:16px">Information widgets are optional and are not transaction records. Provider data may be delayed, incomplete or inaccurate. Verify important information with the original provider. Market prices are not real-time unless explicitly stated by the source.</p>
@endsection

@push('scripts')
<script>
(() => {
    const weatherUrl = @json(route('information.weather'));
    const nearbyUrl = @json(route('information.nearby'));
    const money = value => new Intl.NumberFormat('en-KE', {style:'currency',currency:'KES',maximumFractionDigits:2}).format(value);
    const setMessage = (element, message) => { element.textContent = message; };
    const queryUrl = (url, params) => url + '?' + new URLSearchParams(params).toString();

    const amountInput = document.getElementById('purchasingAmount');
    const yearsInput = document.getElementById('purchasingYears');
    const calculateButton = document.getElementById('calculatePurchasing');
    if (calculateButton && amountInput && yearsInput) {
        calculateButton.addEventListener('click', () => {
            const amount = Number(amountInput.value);
            const years = Number(yearsInput.value);
            const rate = {{ count($inflation) ? (float) end($inflation)['rate'] / 100 : 0 }};
            const output = document.getElementById('purchasingResult');
            if (!Number.isFinite(amount) || amount <= 0 || !Number.isFinite(rate) || rate <= -1) {
                setMessage(output, 'Enter a valid positive amount. Inflation data must also be available.');
                return;
            }
            const purchasingPower = amount / Math.pow(1 + rate, years);
            setMessage(output, money(amount) + ' today would have approximately the purchasing power of ' + money(purchasingPower) + ' after ' + years + ' year(s), assuming the same annual inflation rate. This is an illustration, not a forecast.');
        });
        calculateButton.click();
    }

    async function requestJson(url, params) {
        const response = await fetch(queryUrl(url, params), {headers:{'Accept':'application/json'}});
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'The information service is unavailable.');
        return payload;
    }

    async function getCoordinates() {
        if (!navigator.geolocation) throw new Error('Location is not supported by this browser. Enter an area manually.');
        return new Promise((resolve, reject) => navigator.geolocation.getCurrentPosition(
            position => resolve({latitude:position.coords.latitude,longitude:position.coords.longitude}),
            () => reject(new Error('Location permission was denied or unavailable. Enter an area manually.')),
            {enableHighAccuracy:false,timeout:10000,maximumAge:300000}
        ));
    }

    const weatherResult = document.getElementById('weatherResult');
    async function loadWeather(params) {
        setMessage(weatherResult, 'Loading weather…');
        try {
            const data = await requestJson(weatherUrl, params);
            const current = data.current;
            const days = (data.daily?.time || []).map((date,index) => date + ': ' + data.daily.temperature_2m_min[index] + '° to ' + data.daily.temperature_2m_max[index] + '°C').join(' · ');
            setMessage(weatherResult, (data.location?.name ? data.location.name + ' — ' : '') + current.temperature_2m + '°C, feels like ' + current.apparent_temperature + '°C, humidity ' + current.relative_humidity_2m + '%, wind ' + current.wind_speed_10m + ' km/h. Next days: ' + days + '. Source: ' + data.source + ' (' + data.timezone + ').');
        } catch (error) { setMessage(weatherResult, error.message); }
    }
    document.getElementById('weatherUseLocation').addEventListener('click', async () => {
        setMessage(weatherResult, 'Waiting for location permission…');
        try { await loadWeather(await getCoordinates()); } catch (error) { setMessage(weatherResult, error.message); }
    });
    document.getElementById('weatherSearch').addEventListener('click', () => {
        const place = document.getElementById('weatherPlace').value.trim();
        if (place.length < 2) return setMessage(weatherResult, 'Enter a town or area to search.');
        loadWeather({place});
    });

    const nearbyResult = document.getElementById('nearbyResult');
    async function loadNearby(params) {
        setMessage(nearbyResult, 'Searching nearby locations…');
        try {
            const data = await requestJson(nearbyUrl, {...params,type:document.getElementById('nearbyType').value});
            nearbyResult.replaceChildren();
            const heading = document.createElement('p');
            heading.textContent = data.type.toUpperCase() + ' near ' + data.location.name + ' · ' + data.places.length + ' result(s)';
            nearbyResult.appendChild(heading);
            if (!data.places.length) {
                const empty = document.createElement('p'); empty.textContent = 'No mapped results found in the search radius.'; nearbyResult.appendChild(empty);
            }
            data.places.forEach(place => {
                const row = document.createElement('p');
                const link = document.createElement('a');
                link.textContent = place.name + (place.operator ? ' · ' + place.operator : '');
                link.href = 'https://www.openstreetmap.org/?mlat=' + encodeURIComponent(place.latitude) + '&mlon=' + encodeURIComponent(place.longitude) + '#map=18/' + encodeURIComponent(place.latitude) + '/' + encodeURIComponent(place.longitude);
                link.target = '_blank'; link.rel = 'noopener noreferrer';
                row.appendChild(link); nearbyResult.appendChild(row);
            });
            const source = document.createElement('small'); source.textContent = data.source; nearbyResult.appendChild(source);
        } catch (error) { setMessage(nearbyResult, error.message); }
    }
    document.getElementById('nearbyUseLocation').addEventListener('click', async () => {
        setMessage(nearbyResult, 'Waiting for location permission…');
        try { await loadNearby(await getCoordinates()); } catch (error) { setMessage(nearbyResult, error.message); }
    });
    document.getElementById('nearbySearchForm').addEventListener('submit', event => {
        event.preventDefault();
        const place = document.getElementById('nearbyPlace').value.trim();
        if (place.length < 2) return setMessage(nearbyResult, 'Enter a town or area to search.');
        loadNearby({place});
    });
})();
</script>
@endpush
