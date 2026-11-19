@extends('layouts.app')
@section('title', 'Kenya and market information')
@section('heading', 'Kenya and market information')
@section('content')
<section class="card" style="margin-bottom:16px">
    <div class="eyebrow">Daily reminder · {{ $dailyQuote['date'] }}</div>
    <h2 class="section-heading" style="margin:7px 0">{{ $dailyQuote['text'] }}</h2>
    <p class="muted" style="font-size:10px;margin:0">{{ $dailyQuote['attribution'] }}</p>
</section>

<div class="grid two-col" style="margin-bottom:16px">
    <section class="card">
        <h2 class="section-heading">Kenya inflation tracker</h2>
        <p class="muted" style="font-size:11px">Annual consumer price inflation from the World Bank. Historical values are annual, not monthly.</p>
        <div id="inflation-status" class="muted" style="font-size:11px">Loading public data…</div>
        <div style="height:220px;position:relative;margin-top:10px"><canvas id="inflation-chart" aria-label="Kenya annual inflation history"></canvas></div>
        <div id="inflation-source" class="muted" style="font-size:10px;margin-top:8px"></div>
        <hr style="border:0;border-top:1px solid #edf0f5;margin:16px 0">
        <h3 style="font-size:13px;margin:0 0 10px">Purchasing-power calculator</h3>
        <form id="purchasing-power-form" class="form-grid">
            <div class="field"><label for="pp-amount">Amount today (KES)</label><input id="pp-amount" name="amount" type="number" min="0.01" step="0.01" value="10000" required></div>
            <div class="field"><label for="pp-years">Years ahead</label><input id="pp-years" name="years" type="number" min="1" max="30" value="5" required></div>
            <div><button class="btn" type="submit">Estimate future cost</button></div>
        </form>
        <div id="purchasing-power-result" class="muted" style="font-size:11px;margin-top:10px" aria-live="polite"></div>
    </section>

    <section class="card">
        <h2 class="section-heading">Kenya public holidays</h2>
        <div id="holidays-status" class="muted" style="font-size:11px">Loading calendar…</div>
        <div id="holidays-list" style="max-height:380px;overflow:auto;margin-top:10px"></div>
        <div id="holidays-source" class="muted" style="font-size:10px;margin-top:8px"></div>
    </section>
</div>

<div class="grid two-col" style="margin-bottom:16px">
    <section class="card">
        <h2 class="section-heading">Cryptocurrency reference prices</h2>
        <div id="crypto-status" class="muted" style="font-size:11px">Loading provider data…</div>
        <div id="crypto-list" style="margin-top:12px"></div>
        <div id="crypto-source" class="muted" style="font-size:10px;margin-top:8px"></div>
        <p class="muted" style="font-size:10px;line-height:1.6">Crypto prices are volatile and are shown for information only. This is not a recommendation to buy, sell or hold digital assets.</p>
    </section>
    <section class="card">
        <h2 class="section-heading">Stock market watch</h2>
        <div id="stocks-status" class="muted" style="font-size:11px">Checking optional provider…</div>
        <div id="stocks-list" style="margin-top:12px"></div>
        <div id="stocks-source" class="muted" style="font-size:10px;margin-top:8px"></div>
        <p class="muted" style="font-size:10px">Quotes may be delayed. Markets may be closed. Configure the optional provider key on the server to enable this widget.</p>
    </section>
</div>

<div class="grid two-col" style="margin-bottom:16px">
    <section class="card">
        <h2 class="section-heading">Commodity reference prices</h2>
        <div id="commodities-status" class="muted" style="font-size:11px">Checking optional provider…</div>
        <div id="commodities-list" style="margin-top:12px"></div>
        <div id="commodities-source" class="muted" style="font-size:10px;margin-top:8px"></div>
        <p class="muted" style="font-size:10px">Monthly reference data, not a live trading quote. Configure the optional Alpha Vantage key on the server to enable it.</p>
    </section>
    <section class="card">
        <h2 class="section-heading">Kenya finance and economy news</h2>
        <div id="news-status" class="muted" style="font-size:11px">Loading headlines…</div>
        <div id="news-list" style="margin-top:10px"></div>
        <div id="news-source" class="muted" style="font-size:10px;margin-top:8px"></div>
    </section>
</div>

<div class="grid two-col">
    <section class="card">
        <h2 class="section-heading">Nearby banks and ATMs</h2>
        <p class="muted" style="font-size:11px;line-height:1.6">Your precise location is not requested on page load. Choose the location button to grant browser permission, or search for a town, estate or landmark manually.</p>
        <button class="btn btn-light" type="button" id="nearby-location">Use my location</button>
        <form id="nearby-search" style="display:flex;gap:8px;margin-top:12px">
            <input name="location" type="text" maxlength="120" placeholder="e.g. Westlands, Nairobi" required style="min-width:0;flex:1">
            <button class="btn" type="submit">Search</button>
        </form>
        <div id="nearby-status" class="muted" style="font-size:11px;margin-top:12px" aria-live="polite"></div>
        <div id="nearby-list" style="margin-top:8px"></div>
        <p class="muted" style="font-size:10px;margin-top:10px">OpenStreetMap data may be incomplete or out of date. Confirm opening hours and services with the provider.</p>
    </section>

    <section class="card">
        <h2 class="section-heading">Weather · optional</h2>
        <p class="muted" style="font-size:11px">Weather is an optional dashboard enhancement. Search for a city or choose to share your location with this request only.</p>
        <form id="weather-search" style="display:flex;gap:8px">
            <input name="location" type="text" maxlength="120" placeholder="e.g. Nairobi" required style="min-width:0;flex:1">
            <button class="btn" type="submit">Get weather</button>
        </form>
        <button class="btn btn-light" type="button" id="weather-location" style="margin-top:8px">Use my location</button>
        <div id="weather-result" style="margin-top:14px" aria-live="polite"></div>
    </section>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const endpoint = section => '/market/data/' + encodeURIComponent(section);
    const setText = (id, value) => { const node = document.getElementById(id); if (node) node.textContent = value; };
    const appendLine = (parent, text, strong = false) => {
        const node = document.createElement(strong ? 'strong' : 'p');
        node.textContent = text;
        node.style.cssText = 'display:block;margin:7px 0;font-size:12px;line-height:1.6';
        parent.appendChild(node);
    };
    const showUnavailable = (section, data) => {
        setText(section + '-status', data?.message || 'This information is temporarily unavailable.');
        if (data?.stale) setText(section + '-status', 'Showing cached data; the provider could not be reached.');
    };
    const loadSection = async section => {
        try {
            const response = await fetch(endpoint(section), { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok || data.available === false) {
                showUnavailable(section, data);
                return;
            }
            if (data.stale) setText(section + '-status', 'Cached data · may be out of date');
            else setText(section + '-status', 'Updated ' + (data.fetched_at || data.date || 'today'));
            if (section === 'inflation') {
                const values = data.values || [];
                if (window.Chart && values.length) {
                    new Chart(document.getElementById('inflation-chart'), {
                        type: 'line',
                        data: { labels: values.map(row => row.year), datasets: [{ label: 'Annual inflation (%)', data: values.map(row => row.inflation), tension: 0.25 }] },
                        options: { responsive: true, maintainAspectRatio: false, scales: { y: { title: { display: true, text: '%' } } } }
                    });
                } else {
                    appendLine(document.getElementById('inflation-status'), 'Chart library unavailable; latest annual inflation: ' + data.latest.inflation + '%');
                }
                setText('inflation-source', data.source + ' · Data year ' + data.data_as_of + (data.stale ? ' · stale cache' : ''));
            }
            if (section === 'holidays') {
                const list = document.getElementById('holidays-list');
                (data.holidays || []).forEach(item => appendLine(list, item.date + ' · ' + item.name));
                setText('holidays-source', data.source + ' · ' + data.fetched_at);
            }
            if (section === 'crypto') {
                const list = document.getElementById('crypto-list');
                (data.quotes || []).forEach(item => appendLine(list, item.name + ': USD ' + Number(item.usd).toLocaleString() + (item.kes ? ' · KES ' + Number(item.kes).toLocaleString() : '') + (item.change_24h === null ? '' : ' · 24h ' + Number(item.change_24h).toFixed(2) + '%'), true));
                setText('crypto-source', data.source + ' · ' + data.delay_note + ' · ' + data.fetched_at);
            }
            if (section === 'stocks' || section === 'commodities') {
                const list = document.getElementById(section + '-list');
                (data.quotes || []).forEach(item => appendLine(list, (item.symbol || item.name) + ': ' + item.currency + ' ' + Number(item.price).toLocaleString() + (item.unit ? ' ' + item.unit : '') + (item.change_percent ? ' · ' + item.change_percent : '') + (item.trading_day || item.date ? ' · ' + (item.trading_day || item.date) : ''), true));
                setText(section + '-source', data.source + ' · ' + data.delay_note + ' · ' + data.fetched_at);
            }
            if (section === 'news') {
                const list = document.getElementById('news-list');
                (data.items || []).forEach(item => {
                    const row = document.createElement('div');
                    row.style.cssText = 'padding:9px 0;border-bottom:1px solid #edf0f5';
                    const link = document.createElement('a');
                    link.textContent = item.title;
                    link.href = item.url;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.style.cssText = 'font-size:12px;font-weight:700;color:#263a60;text-decoration:none';
                    row.appendChild(link);
                    appendLine(row, item.publisher + ' · ' + item.published_at);
                    list.appendChild(row);
                });
                setText('news-source', data.source + ' · ' + data.fetched_at);
            }
        } catch (error) {
            showUnavailable(section, null);
        }
    };
    ['inflation', 'holidays', 'crypto', 'stocks', 'commodities', 'news'].forEach(loadSection);

    document.getElementById('purchasing-power-form').addEventListener('submit', async event => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const output = document.getElementById('purchasing-power-result');
        output.textContent = 'Calculating…';
        try {
            const response = await fetch('/market/purchasing-power', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ amount: form.get('amount'), years: form.get('years') })
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Calculation unavailable');
            output.textContent = 'Estimated future cost: KES ' + Number(data.estimated_future_cost).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' · Annual inflation ' + data.annual_inflation + '% (data year ' + data.source_year + '). ' + data.disclaimer;
        } catch (error) { output.textContent = error.message; }
    });

    const postLocation = async (url, payload, statusId, listId, kind) => {
        setText(statusId, 'Searching…');
        const list = document.getElementById(listId);
        list.replaceChildren();
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Search unavailable');
            if (kind === 'nearby') {
                setText(statusId, 'Results near ' + data.searched_location + ' · ' + data.source);
                (data.places || []).forEach(place => appendLine(list, place.name + ' · ' + place.type + ' · ' + place.distance_km + ' km' + (place.address ? ' · ' + place.address : '')));
                if (!data.places?.length) appendLine(list, 'No banks or ATMs were found within 3 km.');
            } else {
                const current = data.current;
                setText(statusId, data.location + ' · ' + data.source + ' · ' + data.fetched_at);
                appendLine(list, current.temperature_2m + '°C · feels like ' + current.apparent_temperature + '°C', true);
                appendLine(list, 'Humidity ' + current.relative_humidity_2m + '% · Wind ' + current.wind_speed_10m + ' km/h · Precipitation ' + current.precipitation + ' mm');
                appendLine(list, data.delay_note);
            }
        } catch (error) { setText(statusId, error.message); }
    };
    const getBrowserLocation = callback => {
        if (!navigator.geolocation) { setText('nearby-status', 'Location is not supported by this browser. Use manual search.'); return; }
        navigator.geolocation.getCurrentPosition(
            position => callback({ latitude: position.coords.latitude, longitude: position.coords.longitude }),
            () => setText('nearby-status', 'Location permission was denied or unavailable. Use manual search.'),
            { enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 }
        );
    };
    document.getElementById('nearby-location').addEventListener('click', () => {
        setText('nearby-status', 'Requesting location permission after your action…');
        getBrowserLocation(coords => postLocation('/market/nearby', coords, 'nearby-status', 'nearby-list', 'nearby'));
    });
    document.getElementById('nearby-search').addEventListener('submit', event => {
        event.preventDefault();
        const location = new FormData(event.currentTarget).get('location');
        postLocation('/market/nearby', { location }, 'nearby-status', 'nearby-list', 'nearby');
    });
    document.getElementById('weather-location').addEventListener('click', () => {
        setText('weather-result', 'Requesting location permission after your action…');
        getBrowserLocation(coords => postLocation('/market/weather', coords, 'weather-result', 'weather-result', 'weather'));
    });
    document.getElementById('weather-search').addEventListener('submit', event => {
        event.preventDefault();
        const location = new FormData(event.currentTarget).get('location');
        postLocation('/market/weather', { location }, 'weather-result', 'weather-result', 'weather');
    });
})();
</script>
@endpush
