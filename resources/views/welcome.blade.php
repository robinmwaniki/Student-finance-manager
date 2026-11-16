<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Student Finance Manager') }}</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f5f7fb; color: #172033; }
        main { width: min(680px, calc(100% - 32px)); padding: 42px; border: 1px solid #e3e8f0; border-radius: 20px; background: #fff; box-shadow: 0 20px 60px #1720330d; }
        .eyebrow { color: #5365b5; font-size: 12px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        h1 { margin: 14px 0; font-size: clamp(30px, 5vw, 46px); letter-spacing: -.04em; }
        p { color: #64748b; line-height: 1.7; }
        .status { display: inline-flex; align-items: center; gap: 8px; margin-top: 18px; padding: 9px 12px; border-radius: 999px; background: #edfdf4; color: #167747; font-size: 13px; font-weight: 600; }
        .dot { width: 7px; height: 7px; border-radius: 50%; background: #22a06b; }
    </style>
</head>
<body>
<main>
    <div class="eyebrow">Application rebuild in progress</div>
    <h1>Student Finance Manager</h1>
    <p>The Laravel foundation is in place. Existing finance workflows and records are being migrated in controlled stages to PostgreSQL.</p>
    <div class="status"><span class="dot"></span> Laravel application is responding</div>
</main>
</body>
</html>
