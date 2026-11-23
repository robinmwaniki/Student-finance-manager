<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Student Finance Manager') · {{ config('app.name') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root{font-family:'DM Sans',sans-serif;color:#172033;background:#f5f7fb;font-synthesis:none;text-rendering:optimizeLegibility}*{box-sizing:border-box}body{margin:0}.shell{min-height:100vh}.sidebar{position:fixed;inset:0 auto 0 0;width:248px;padding:26px 18px;background:#fff;border-right:1px solid #e8edf4;display:flex;flex-direction:column;z-index:5}.brand{font:700 19px 'Space Grotesk',sans-serif;letter-spacing:-.6px;padding:4px 12px 30px;color:#18243b}.brand-mark{display:inline-grid;place-items:center;width:34px;height:34px;margin-right:9px;border-radius:11px;background:#202f53;color:white}.nav-label{padding:12px;color:#94a0b3;font-size:10px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase}.nav-link{display:flex;align-items:center;gap:12px;padding:11px 13px;margin:2px 0;border-radius:9px;color:#64748b;text-decoration:none;font-size:13px;font-weight:600}.nav-link:hover,.nav-link.active{background:#f0f3fa;color:#24385f}.nav-icon{width:18px;text-align:center;font-size:15px}.sidebar-bottom{margin-top:auto;border-top:1px solid #edf0f5;padding-top:15px}.user-chip{padding:12px;color:#334155;font-size:12px}.user-chip small{display:block;color:#94a3b8;margin-top:3px}.main{margin-left:248px;min-height:100vh;padding:32px clamp(18px,4vw,52px) 60px;max-width:1600px}.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px}.eyebrow{font-size:11px;color:#8490a3;font-weight:700;text-transform:uppercase;letter-spacing:1px}.page-title{font:600 27px 'Space Grotesk',sans-serif;letter-spacing:-.8px;margin:5px 0 0}.muted{color:#8490a3}.card{background:#fff;border:1px solid #e8edf4;border-radius:14px;padding:21px;box-shadow:0 5px 18px #17203305}.grid{display:grid;gap:16px}.stats{grid-template-columns:repeat(4,minmax(0,1fr))}.stat-label{font-size:12px;color:#8490a3;font-weight:600}.stat-value{font:600 24px 'Space Grotesk',sans-serif;margin-top:12px;letter-spacing:-.7px}.stat-note{font-size:11px;color:#94a3b8;margin-top:5px}.section-heading{font:600 17px 'Space Grotesk',sans-serif;margin:0 0 17px}.two-col{grid-template-columns:1fr 1fr;margin-top:18px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:8px;padding:10px 15px;background:#24385f;color:#fff;text-decoration:none;font:600 12px 'DM Sans',sans-serif;cursor:pointer}.btn:hover{background:#172746}.btn-light{background:#f1f4f9;color:#43516a}.btn-danger{background:#fff1f1;color:#b42318}.btn-small{padding:7px 10px;font-size:11px}.form-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;align-items:end}.field label{display:block;font-size:11px;font-weight:700;color:#64748b;margin-bottom:7px}.field input,.field select{width:100%;height:41px;border:1px solid #dfe5ee;border-radius:8px;padding:0 11px;font:13px 'DM Sans',sans-serif;color:#172033;background:#fff}.field input:focus{outline:2px solid #dbe5fa;border-color:#879cc5}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;font-size:12px}th{text-align:left;color:#94a0b3;font-size:10px;text-transform:uppercase;letter-spacing:.7px;padding:11px 10px;border-bottom:1px solid #edf0f5}td{padding:13px 10px;border-bottom:1px solid #f0f2f6;color:#475569}tr:last-child td{border-bottom:0}.amount{font-weight:700;color:#1e293b}.alert{padding:12px 15px;border-radius:9px;margin-bottom:16px;font-size:12px}.alert-success{background:#ecfdf3;color:#087443}.alert-error{background:#fff1f1;color:#b42318}.errors{margin:0 0 15px;padding-left:18px;color:#b42318;font-size:12px}.pagination{display:flex;gap:6px;margin-top:16px;font-size:12px}.pagination a,.pagination span{padding:7px 10px;border:1px solid #e3e8f0;border-radius:7px;color:#52617a;text-decoration:none}.auth-page{min-height:100vh;display:grid;place-items:center;padding:25px;background:#f5f7fb}.auth-card{width:min(430px,100%);background:#fff;border:1px solid #e8edf4;border-radius:16px;padding:32px;box-shadow:0 20px 55px #1720330a}.auth-card h1{font:600 25px 'Space Grotesk',sans-serif;margin:0 0 7px}.auth-card p{font-size:13px;color:#8490a3;margin:0 0 22px}.auth-card .field{margin-bottom:15px}.auth-card .field input{height:45px}.auth-card .btn{width:100%;height:43px}.auth-foot{text-align:center;margin-top:18px;font-size:12px;color:#8490a3}.auth-foot a{color:#344a75;text-decoration:none;font-weight:700}
@media(max-width:1050px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:760px){.sidebar{position:static;width:auto;height:auto;padding:12px 15px;display:block;border-right:0;border-bottom:1px solid #e8edf4}.brand{padding:5px 3px 12px}.nav-label,.sidebar-bottom{display:none}.nav-items{display:flex;overflow:auto;gap:4px}.nav-link{white-space:nowrap;padding:8px 10px}.main{margin-left:0;padding:23px 15px 40px}.topbar{margin-bottom:20px}.two-col{grid-template-columns:1fr}}@media(max-width:480px){.stats{grid-template-columns:1fr 1fr;gap:9px}.card{padding:15px}.stat-value{font-size:19px}.form-grid{grid-template-columns:1fr}.page-title{font-size:23px}}
</style>
<style>
body[data-theme="dark"]{background:#0b1220;color:#e5eaf3}
body[data-theme="dark"] .sidebar{background:#111b2c;border-color:#263449}
body[data-theme="dark"] .brand,body[data-theme="dark"] .user-chip{color:#e5eaf3}
body[data-theme="dark"] .nav-link{color:#a9b6ca}
body[data-theme="dark"] .nav-link:hover,body[data-theme="dark"] .nav-link.active{background:#24334b;color:#f8fafc}
body[data-theme="dark"] .main{color:#e5eaf3}
body[data-theme="dark"] .card,body[data-theme="dark"] .auth-card{background:#142035;border-color:#293950;box-shadow:0 5px 18px #00000020}
body[data-theme="dark"] .section-heading,body[data-theme="dark"] .stat-value,body[data-theme="dark"] .amount,body[data-theme="dark"] strong{color:#e5eaf3}
body[data-theme="dark"] .muted,body[data-theme="dark"] .eyebrow,body[data-theme="dark"] .stat-label,body[data-theme="dark"] .stat-note{color:#9aabc3}
body[data-theme="dark"] input,body[data-theme="dark"] select,body[data-theme="dark"] textarea{background:#0f1a2b;color:#e5eaf3;border-color:#34465f}
body[data-theme="dark"] td{color:#c2ccdb;border-color:#293950}
body[data-theme="dark"] th{color:#91a2bb;border-color:#293950}
body[data-theme="dark"] .btn-light{background:#25354d;color:#dbe6f5}
body[data-theme="dark"] .btn-light:hover{background:#314561}
body[data-theme="dark"] .info-result,body[data-theme="dark"] .alert{background:#1c2b42;color:#dbe6f5}
body[data-theme="dark"] .sidebar-bottom{border-color:#293950}
body[data-theme="dark"] .settings-field label{color:#a9b6ca}
body[data-theme="dark"] .settings-check{color:#c5d0df;border-color:#293950}
body[data-theme="dark"] .settings-check small{color:#94a3b8}
body[data-theme="dark"] .avatar-preview{border-color:#34465f;background:#1c2b42}
</style>
@stack('head')
<link rel="manifest" href="/manifest.webmanifest"><meta name="theme-color" content="#24385f">
</head><body data-theme="{{ auth()->check() ? auth()->user()->theme : 'light' }}" @auth data-user-id="{{ auth()->id() }}" @endauth><div class="shell">
@auth
<aside class="sidebar"><div class="brand"><span class="brand-mark">SF</span>Student Finance</div><div class="nav-label">Workspace</div>
<nav class="nav-items">
<a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="nav-icon">▦</span>Overview</a>
<a class="nav-link {{ request()->routeIs('income.*') ? 'active' : '' }}" href="{{ route('income.index') }}"><span class="nav-icon">↗</span>Income</a>
<a class="nav-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}" href="{{ route('expenses.index') }}"><span class="nav-icon">↘</span>Expenses</a>
<a class="nav-link {{ request()->routeIs('wallets.*') ? 'active' : '' }}" href="{{ route('wallets.index') }}"><span class="nav-icon">▣</span>Wallets</a>
<a class="nav-link {{ request()->routeIs('imports.*') ? 'active' : '' }}" href="{{ route('imports.index') }}"><span class="nav-icon">⇧</span>Import CSV</a>
<a class="nav-link {{ request()->routeIs('expense-capture.*') ? 'active' : '' }}" href="{{ route('expense-capture.index') }}"><span class="nav-icon">⌁</span>Capture</a>
<a class="nav-link {{ request()->routeIs('budgets.*') ? 'active' : '' }}" href="{{ route('budgets.index') }}"><span class="nav-icon">▥</span>Budgets</a>
<a class="nav-link {{ request()->routeIs('savings.*') ? 'active' : '' }}" href="{{ route('savings.index') }}"><span class="nav-icon">◎</span>Savings</a>
<a class="nav-link {{ request()->routeIs('debts.*') ? 'active' : '' }}" href="{{ route('debts.index') }}"><span class="nav-icon">▤</span>Debts</a>
<a class="nav-link {{ request()->routeIs('recurring.*') ? 'active' : '' }}" href="{{ route('recurring.index') }}"><span class="nav-icon">⟳</span>Recurring</a>
<a class="nav-link {{ request()->routeIs('reminders.*') ? 'active' : '' }}" href="{{ route('reminders.index') }}"><span class="nav-icon">◷</span>Reminders</a>
<a class="nav-link {{ request()->routeIs('journal.*') ? 'active' : '' }}" href="{{ route('journal.index') }}"><span class="nav-icon">▤</span>Journal</a>
<a class="nav-link {{ request()->routeIs('tools.*') ? 'active' : '' }}" href="{{ route('tools.index') }}"><span class="nav-icon">∑</span>Tools</a>
<a class="nav-link {{ request()->routeIs('market.*') ? 'active' : '' }}" href="{{ route('market.index') }}"><span class="nav-icon">◎</span>Market info</a>
<a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}"><span class="nav-icon">▤</span>Reports</a>
<a class="nav-link {{ request()->routeIs('learning.*') ? 'active' : '' }}" href="{{ route('learning.index') }}"><span class="nav-icon">✦</span>Learning</a>
<a class="nav-link {{ request()->routeIs('assistant.*') ? 'active' : '' }}" href="{{ route('assistant.index') }}"><span class="nav-icon">✧</span>AI Assistant</a>
<a class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}" href="{{ route('notifications.index') }}"><span class="nav-icon">♧</span>Notifications @php($unreadNavCount = auth()->user()->userNotifications()->where('status', 'unread')->count()) @if($unreadNavCount > 0)<span style="margin-left:auto;background:#e9efff;color:#344a75;border-radius:10px;padding:2px 6px;font-size:10px">{{ $unreadNavCount }}</span>@endif</a>
<a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}"><span class="nav-icon">⚙</span>Settings</a>
</nav>
</aside>
@endauth
<main class="{{ auth()->check() ? 'main' : '' }}">
@auth<header class="topbar"><div><div class="eyebrow">Personal finance workspace</div><h1 class="page-title">@yield('heading', 'Overview')</h1></div><div class="muted" style="font-size:12px">{{ now(config('app.timezone'))->format('D, d M Y') }}</div></header>@endauth
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-error"><ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</main></div>
@auth
<div id="offline-sync-panel" role="status" aria-live="polite" style="position:fixed;right:18px;bottom:18px;z-index:20;max-width:min(360px,calc(100vw - 36px));background:#fff;border:1px solid #dce3ed;border-radius:12px;padding:12px 14px;box-shadow:0 8px 28px #17203318;font-size:12px;color:#334155">
<div style="display:flex;align-items:center;justify-content:space-between;gap:10px"><strong id="offline-sync-state">Online</strong><button id="offline-sync-now" type="button" class="btn btn-small">Sync now</button></div>
<div id="offline-sync-detail" class="muted" style="margin-top:5px">Offline transactions stay on this device until they sync. Do not use this feature on a shared device.</div>
<div id="offline-sync-items" style="margin-top:8px;max-height:180px;overflow:auto"></div>
</div>
<script src="/offline-retry-policy.js" defer></script>
<script src="/offline-transactions.js" defer></script>
@endauth
<script>if ('serviceWorker' in navigator) { window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {})); }</script>
@stack('scripts')</body></html>
