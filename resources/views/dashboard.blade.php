@extends('layouts.app')
@section('title', 'Dashboard')
@section('heading', 'Financial overview')
@section('content')
<div class="card" style="margin-bottom:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap">
        <div><div class="eyebrow">Selected reporting period</div><h2 class="section-heading" style="margin:5px 0 0">{{ $monthLabel }}</h2></div>
        <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <label for="month" class="muted" style="font-size:12px">Month</label>
            <input id="month" name="month" type="month" value="{{ $month }}" required>
            <button class="btn btn-small" type="submit">Apply</button>
        </form>
    </div>
    <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap">
        <a class="btn btn-light btn-small" href="{{ route('dashboard', ['month' => \Carbon\CarbonImmutable::parse($month.'-01')->subMonth()->format('Y-m')]) }}">← {{ \Carbon\CarbonImmutable::parse($month.'-01')->subMonth()->format('M Y') }}</a>
        <a class="btn btn-light btn-small" href="{{ route('dashboard', ['month' => \Carbon\CarbonImmutable::parse($month.'-01')->addMonth()->format('Y-m')]) }}">{{ $nextMonthLabel }} →</a>
    </div>
</div>

<div class="grid stats" style="margin-bottom:18px">
    <section class="card"><div class="stat-label">Income</div><div class="stat-value">KES {{ number_format($totalIncome, 2) }}</div>
        <div class="stat-note">Received during {{ $monthLabel }}</div>
        @if($comparison['income']['percentage'] === null)<small class="muted">No previous-month baseline</small>@else<small class="muted">{{ $comparison['income']['difference'] >= 0 ? '▲' : '▼' }} KES {{ number_format(abs($comparison['income']['difference']), 2) }} ({{ number_format($comparison['income']['percentage'], 1) }}%) vs {{ $previousMonthLabel }}</small>@endif
    </section>
    <section class="card"><div class="stat-label">Expenses</div><div class="stat-value">KES {{ number_format($totalExpenses, 2) }}</div>
        <div class="stat-note">Spent during {{ $monthLabel }}</div>
        @if($comparison['expenses']['percentage'] === null)<small class="muted">No previous-month baseline</small>@else<small class="muted">{{ $comparison['expenses']['difference'] >= 0 ? '▲' : '▼' }} KES {{ number_format(abs($comparison['expenses']['difference']), 2) }} ({{ number_format($comparison['expenses']['percentage'], 1) }}%) vs {{ $previousMonthLabel }}</small>@endif
    </section>
    <section class="card"><div class="stat-label">Savings contributions</div><div class="stat-value">KES {{ number_format($totalSavings, 2) }}</div>
        <div class="stat-note">Saved during {{ $monthLabel }}</div>
        @if($comparison['savings']['percentage'] === null)<small class="muted">No previous-month baseline</small>@else<small class="muted">{{ $comparison['savings']['difference'] >= 0 ? '▲' : '▼' }} KES {{ number_format(abs($comparison['savings']['difference']), 2) }} ({{ number_format($comparison['savings']['percentage'], 1) }}%) vs {{ $previousMonthLabel }}</small>@endif
    </section>
    <section class="card"><div class="stat-label">Budget limits</div><div class="stat-value">KES {{ number_format($totalBudget, 2) }}</div><div class="stat-note">Total category limits for the month</div></section>
    <section class="card"><div class="stat-label">Available balance</div><div class="stat-value" style="color:{{ $netBalance >= 0 ? '#16835b' : '#c24141' }}">KES {{ number_format($netBalance, 2) }}</div><div class="stat-note">Income less expenses and savings</div></section>
</div>

<div class="grid two-col" style="margin-top:0;margin-bottom:18px">
    <section class="card">
        <h2 class="section-heading">Cash-flow estimate · {{ $forecastMonthLabel }}</h2>
        <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
            <div><div class="stat-label">Estimated income</div><div class="stat-value" style="font-size:19px">KES {{ number_format($forecast['income'], 2) }}</div></div>
            <div><div class="stat-label">Estimated expenses</div><div class="stat-value" style="font-size:19px">KES {{ number_format($forecast['expenses'], 2) }}</div></div>
            <div><div class="stat-label">Estimated savings</div><div class="stat-value" style="font-size:19px">KES {{ number_format($forecast['savings'], 2) }}</div></div>
            <div><div class="stat-label">Estimated net balance</div><div class="stat-value" style="font-size:19px;color:{{ $forecast['balance'] >= 0 ? '#16835b' : '#c24141' }}">KES {{ number_format($forecast['balance'], 2) }}</div></div>
        </div>
        <p class="muted" style="font-size:11px;line-height:1.6;margin:14px 0 0">Estimate, not an actual result. Uses a simple average of the three complete months before the selected month ({{ $forecastBasis }}); zero-activity months are included. It assumes no growth or seasonal adjustment.</p>
    </section>
    <section class="card">
        <h2 class="section-heading">Budget alerts</h2>
        @forelse($budgetAlerts as $alert)
            <div style="padding:11px 0;border-bottom:1px solid #f0f2f6">
                <div style="display:flex;justify-content:space-between;gap:10px;align-items:center"><strong style="font-size:12px">{{ $alert->category }}</strong><span style="font-size:11px;font-weight:700;color:{{ $alert->level === 'exceeded' ? '#b42318' : '#b7791f' }}">{{ $alert->level === 'exceeded' ? 'Over budget' : 'Near limit' }} · {{ number_format($alert->usage, 1) }}%</span></div>
                <div style="height:7px;background:#edf0f5;border-radius:8px;overflow:hidden;margin:8px 0"><div style="height:100%;width:{{ min(100, max(0, $alert->usage)) }}%;background:{{ $alert->level === 'exceeded' ? '#b42318' : '#d97706' }}"></div></div>
                <small class="muted">Spent KES {{ number_format($alert->spent, 2) }} of KES {{ number_format($alert->budget, 2) }} · Remaining KES {{ number_format($alert->remaining, 2) }}</small>
            </div>
        @empty
            <p class="muted" style="font-size:12px">No budget alerts. Categories below 80% usage are not flagged.</p>
        @endforelse
        <a href="{{ route('budgets.index', ['month' => $month]) }}" class="btn btn-light btn-small" style="margin-top:12px">Manage budgets</a>
    </section>
</div>

<section class="card" style="margin-bottom:18px">
    <h2 class="section-heading">Daily cash flow · {{ $monthLabel }}</h2>
    <p class="muted" style="font-size:11px;margin-top:-8px">Daily income, expenses and savings recorded in the selected month.</p>
    <div style="height:300px;position:relative"><canvas id="dailyFinanceChart" role="img" aria-label="Daily income, expenses and savings chart for {{ $monthLabel }}"></canvas></div>
    <details style="margin-top:12px"><summary style="cursor:pointer;font-size:12px;font-weight:700;color:#52617a">View daily data table</summary>
        <div class="table-wrap" style="margin-top:10px"><table><thead><tr><th>Day</th><th>Income (KES)</th><th>Expenses (KES)</th><th>Savings (KES)</th></tr></thead><tbody>
        @foreach($dailyLabels as $index => $day)<tr><td>{{ $day }}</td><td>{{ number_format($dailyIncome[$index], 2) }}</td><td>{{ number_format($dailyExpenses[$index], 2) }}</td><td>{{ number_format($dailySavings[$index], 2) }}</td></tr>@endforeach
        </tbody></table></div>
    </details>
</section>

<div class="grid two-col" style="margin-top:0;margin-bottom:18px">
    <section class="card">
        <h2 class="section-heading">Expense categories</h2>
        @if($categoryBreakdown->isNotEmpty())
            <div style="height:250px;position:relative"><canvas id="categoryFinanceChart" role="img" aria-label="Expense totals by category for {{ $monthLabel }}"></canvas></div>
            <div class="table-wrap" style="margin-top:12px"><table><thead><tr><th>Category</th><th>Spent (KES)</th></tr></thead><tbody>
            @foreach($categoryBreakdown as $item)<tr><td>{{ $item->category }}</td><td class="amount">{{ number_format((float) $item->total, 2) }}</td></tr>@endforeach
            </tbody></table></div>
        @else
            <p class="muted" style="font-size:12px">No expenses recorded for this month yet.</p>
        @endif
    </section>
    <section class="card">
        <h2 class="section-heading">Month-over-month comparison</h2>
        <p class="muted" style="font-size:11px;margin-top:-8px">Selected month compared with {{ $previousMonthLabel }}.</p>
        <div class="table-wrap"><table><thead><tr><th>Metric</th><th>Previous</th><th>Selected</th><th>Change</th></tr></thead><tbody>
        @foreach(['income' => 'Income', 'expenses' => 'Expenses', 'savings' => 'Savings', 'budget' => 'Budget', 'balance' => 'Net balance'] as $key => $label)
            <tr><td>{{ $label }}</td><td>KES {{ number_format($previous[$key], 2) }}</td><td>KES {{ number_format($key === 'balance' ? $netBalance : ($key === 'income' ? $totalIncome : ($key === 'expenses' ? $totalExpenses : ($key === 'savings' ? $totalSavings : $totalBudget))), 2) }}</td>
            <td>@if($comparison[$key]['percentage'] === null)<span class="muted">New · no baseline</span>@else{{ $comparison[$key]['difference'] >= 0 ? '+' : '−' }}KES {{ number_format(abs($comparison[$key]['difference']), 2) }} ({{ number_format($comparison[$key]['percentage'], 1) }}%)@endif</td></tr>
        @endforeach
        </tbody></table></div>
    </section>
</div>

<section class="card">
    <h2 class="section-heading">Recent activity</h2>
    <div class="table-wrap"><table><thead><tr><th>Type</th><th>Description</th><th>Date</th><th>Amount (KES)</th></tr></thead><tbody>
    @forelse($recentActivity as $item)
        <tr><td>{{ $item['type'] }}</td><td>{{ $item['label'] }}</td><td>{{ $item['date']->format('d M Y') }}</td><td class="amount" style="color:{{ $item['type'] === 'Income' ? '#16835b' : '#475569' }}">{{ $item['type'] === 'Income' ? '+' : '−' }}{{ number_format($item['amount'], 2) }}</td></tr>
    @empty<tr><td colspan="4" class="muted">No financial activity yet. Add an income, expense or savings entry to get started.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartOptions = { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false } };
new Chart(document.getElementById('dailyFinanceChart'), {
    type: 'line',
    data: {
        labels: @json($dailyLabels),
        datasets: [
            { label: 'Income', data: @json($dailyIncome), borderColor: '#16835b', backgroundColor: '#16835b18', fill: true, tension: 0.25 },
            { label: 'Expenses', data: @json($dailyExpenses), borderColor: '#c24141', backgroundColor: '#c2414118', fill: true, tension: 0.25 },
            { label: 'Savings', data: @json($dailySavings), borderColor: '#5365b5', backgroundColor: '#5365b518', fill: true, tension: 0.25 }
        ]
    },
    options: { ...chartOptions, scales: { y: { beginAtZero: true, title: { display: true, text: 'KES' } }, x: { title: { display: true, text: 'Day of month' } } } }
});
@if($categoryBreakdown->isNotEmpty())
new Chart(document.getElementById('categoryFinanceChart'), {
    type: 'doughnut',
    data: { labels: @json($categoryBreakdown->pluck('category')->values()), datasets: [{ label: 'Expenses (KES)', data: @json($categoryBreakdown->pluck('total')->map(fn ($value) => (float) $value)->values()) }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
});
@endif
</script>
@endpush
