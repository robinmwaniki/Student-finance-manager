<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f7f8fc">
    <meta name="description" content="Understand your money, plan your spending and build better financial habits with Student Finance Manager.">
    <title>{{ config('app.name', 'Student Finance Manager') }} — Make your money make sense</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--ink:#172033;--muted:#68748a;--line:#e8ebf2;--blue:#4f46e5;--blue-dark:#3730a3;--mint:#dff8ed;--paper:#fff;--bg:#f7f8fc;--shadow:0 24px 70px rgba(31,41,75,.12)}
        *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--bg);color:var(--ink);font-family:"DM Sans",sans-serif;-webkit-font-smoothing:antialiased}a{color:inherit;text-decoration:none}button,a{-webkit-tap-highlight-color:transparent}.shell{width:min(1160px,calc(100% - 48px));margin:auto}
        .nav{height:82px;display:flex;align-items:center;justify-content:space-between}.brand{display:flex;align-items:center;gap:11px;font:800 18px Manrope,sans-serif;letter-spacing:-.6px}.brand-mark{width:36px;height:36px;display:grid;place-items:center;border-radius:12px;background:var(--blue);color:#fff;box-shadow:0 7px 18px #4f46e544}.brand-mark svg{width:21px}.nav-links{display:flex;align-items:center;gap:30px;color:#647087;font-size:14px;font-weight:600}.nav-actions{display:flex;gap:11px;align-items:center}.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:12px 18px;border-radius:11px;font-size:14px;font-weight:700;transition:transform .2s,box-shadow .2s,background .2s}.btn:hover{transform:translateY(-2px)}.btn-quiet{color:#45516a}.btn-primary{background:var(--blue);color:#fff;box-shadow:0 9px 20px #4f46e533}.btn-primary:hover{background:var(--blue-dark);box-shadow:0 13px 25px #4f46e544}.btn-outline{border:1px solid #dfe3ec;background:#fff;color:#344056}
        .hero{position:relative;display:grid;grid-template-columns:1fr 1.06fr;align-items:center;gap:44px;padding:76px 0 90px;min-height:630px}.hero-copy{position:relative;z-index:2}.pill{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid #e4e6f4;border-radius:999px;background:#fff;color:#5555a6;font-size:12px;font-weight:700;letter-spacing:.02em}.pill-dot{width:7px;height:7px;border-radius:50%;background:#20b77a;box-shadow:0 0 0 4px #20b77a1b}.hero h1{max-width:570px;margin:22px 0 18px;font:800 clamp(42px,5.2vw,66px)/1.06 Manrope,sans-serif;letter-spacing:-3.8px}.hero h1 span{color:var(--blue)}.hero-copy>p{max-width:485px;margin:0;color:var(--muted);font-size:17px;line-height:1.8}.hero-actions{display:flex;gap:12px;margin-top:30px;flex-wrap:wrap}.trust{display:flex;align-items:center;gap:10px;margin-top:28px;color:#8490a3;font-size:12px}.trust svg{width:16px;color:#169b68}
        .visual{position:relative;min-height:475px;display:grid;place-items:center}.orb{position:absolute;width:420px;height:420px;border-radius:50%;background:radial-gradient(circle at 38% 32%,#e8eaff 0,#e4e7ff 34%,#f0f1ff 65%,#f7f8fc 72%);filter:blur(1px)}.dashboard{position:relative;z-index:1;width:min(510px,100%);padding:22px;border:1px solid #ffffffd9;border-radius:22px;background:#ffffffed;box-shadow:var(--shadow);backdrop-filter:blur(18px);transform:rotate(-2deg);animation:card-enter .9s cubic-bezier(.2,.8,.2,1) both}.dash-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}.dash-title{font:800 14px Manrope}.dash-sub{margin-top:4px;color:#9aa3b3;font-size:10px}.avatar{width:30px;height:30px;border-radius:50%;background:#e9eaff;color:#4f46e5;display:grid;place-items:center;font-size:11px;font-weight:800}.balance{padding:18px;border-radius:15px;background:linear-gradient(115deg,#24265b,#4d4ab4);color:white;overflow:hidden;position:relative}.balance:after{content:"";position:absolute;width:170px;height:170px;border:1px solid #ffffff20;border-radius:50%;right:-35px;top:-105px;box-shadow:0 0 0 22px #ffffff08,0 0 0 45px #ffffff06}.balance-label{font-size:10px;color:#d5d6f8}.balance-amount{margin:7px 0 12px;font:800 27px Manrope;letter-spacing:-1px}.balance-foot{display:flex;align-items:center;gap:6px;color:#b7f4d8;font-size:10px}.balance-foot span{color:#e3e5ff}.dash-stats{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:13px 0}.stat{padding:13px;border:1px solid #edf0f5;border-radius:12px}.stat-label{color:#8993a6;font-size:10px}.stat-value{margin-top:6px;font:800 16px Manrope}.stat-meta{margin-top:4px;font-size:9px;color:#16a06b}.chart-head{display:flex;justify-content:space-between;align-items:center;margin:17px 0 8px;font-size:11px;font-weight:700}.chart-head span:last-child{color:#9aa3b3;font-size:9px;font-weight:500}.chart{width:100%;height:106px;overflow:visible}.chart path.line{fill:none;stroke:#625cf0;stroke-width:3;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:420;stroke-dashoffset:420;animation:draw 2.1s .5s ease forwards}.chart path.area{fill:url(#areaFill);opacity:0;animation:fade-in 1s 1.2s forwards}.chart .gridline{stroke:#eef0f6;stroke-width:1}.float-card{position:absolute;z-index:2;padding:13px 15px;border:1px solid #fff;border-radius:14px;background:#ffffffed;box-shadow:0 15px 38px #25305a1a;backdrop-filter:blur(12px);animation:float 5s ease-in-out infinite}.float-card.income{left:-7px;top:86px}.float-card.savings{right:-7px;bottom:70px;animation-delay:-2.3s}.float-kicker{display:flex;align-items:center;gap:7px;color:#8791a5;font-size:9px;font-weight:600}.float-icon{width:25px;height:25px;display:grid;place-items:center;border-radius:8px;background:#e4f8ee;color:#169b68}.float-icon.purple{background:#eeedff;color:#5b52dc}.float-value{margin-top:8px;font:800 15px Manrope}.float-note{margin-top:3px;color:#1b9b69;font-size:9px}.spark{position:absolute;width:9px;height:9px;border-radius:3px;background:#aaa7ff;opacity:.8;animation:twinkle 3s ease-in-out infinite}.spark.one{top:53px;right:55px}.spark.two{bottom:57px;left:35px;width:6px;height:6px;animation-delay:1s}
        .section{padding:76px 0}.section-head{max-width:620px;margin:0 auto 38px;text-align:center}.eyebrow{color:var(--blue);font-size:11px;font-weight:800;letter-spacing:.15em;text-transform:uppercase}.section h2{margin:12px 0;font:800 clamp(28px,3.5vw,40px)/1.15 Manrope;letter-spacing:-1.6px}.section-head p{margin:0;color:var(--muted);line-height:1.7}.features{display:grid;grid-template-columns:repeat(3,1fr);gap:17px}.feature{padding:25px;border:1px solid var(--line);border-radius:17px;background:#fff;transition:transform .25s,box-shadow .25s}.feature:hover{transform:translateY(-5px);box-shadow:0 18px 40px #27345a0d}.feature-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:13px;background:#efefff;color:var(--blue)}.feature-icon svg{width:20px;height:20px}.feature h3{margin:19px 0 8px;font:800 16px Manrope}.feature p{margin:0;color:var(--muted);font-size:13px;line-height:1.75}
        .band{padding:34px 0 78px}.band-inner{display:flex;align-items:center;justify-content:space-between;gap:28px;padding:34px 40px;border-radius:22px;background:#20234f;color:white;overflow:hidden;position:relative}.band-inner:after{content:"";position:absolute;width:280px;height:280px;right:10%;top:-190px;border:1px solid #ffffff24;border-radius:50%;box-shadow:0 0 0 28px #ffffff08,0 0 0 58px #ffffff06}.band h2{position:relative;z-index:1;margin:0 0 8px;font:800 25px Manrope;letter-spacing:-.8px}.band p{position:relative;z-index:1;margin:0;color:#c5c8e4;font-size:13px;line-height:1.7}.band .btn{position:relative;z-index:1;white-space:nowrap;background:#fff;color:#30316e}
        footer{padding:25px 0 35px;border-top:1px solid var(--line);color:#8791a4;font-size:12px}.footer-row{display:flex;align-items:center;justify-content:space-between;gap:18px}.footer-links{display:flex;gap:20px}
        .reveal{opacity:0;transform:translateY(18px);transition:opacity .7s ease,transform .7s ease}.reveal.visible{opacity:1;transform:translateY(0)}
        @keyframes float{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-12px) rotate(1deg)}}@keyframes draw{to{stroke-dashoffset:0}}@keyframes fade-in{to{opacity:1}}@keyframes twinkle{0%,100%{transform:scale(.7);opacity:.3}50%{transform:scale(1.25);opacity:1}}@keyframes card-enter{from{opacity:0;transform:translateY(22px) rotate(-4deg)}to{opacity:1;transform:translateY(0) rotate(-2deg)}}
        @media(max-width:900px){.hero{grid-template-columns:1fr;gap:18px;padding:62px 0 55px}.hero-copy{text-align:center}.hero-copy>p{margin:auto}.hero h1{margin-left:auto;margin-right:auto}.hero-actions,.trust{justify-content:center}.visual{min-height:430px}.features{grid-template-columns:repeat(2,1fr)}.nav-links{display:none}}
        @media(max-width:560px){.shell{width:min(100% - 32px,1160px)}.nav{height:70px}.brand{font-size:15px}.nav-actions{gap:4px}.nav-actions .btn{padding:10px 12px;font-size:12px}.nav-actions .btn-quiet{display:none}.hero{padding:48px 0 30px;min-height:auto}.hero h1{font-size:43px;letter-spacing:-2.5px}.hero-copy>p{font-size:15px}.visual{min-height:345px;margin:4px -7px 0}.orb{width:290px;height:290px}.dashboard{padding:14px;border-radius:17px}.balance{padding:14px}.balance-amount{font-size:22px}.chart{height:80px}.float-card{padding:10px 11px}.float-card.income{left:0;top:38px}.float-card.savings{right:0;bottom:25px}.float-value{font-size:13px}.section{padding:52px 0}.features{grid-template-columns:1fr}.feature{padding:21px}.band{padding:10px 0 52px}.band-inner{align-items:flex-start;flex-direction:column;padding:27px 23px}.band h2{font-size:22px}.footer-row{align-items:flex-start;flex-direction:column}.footer-links{gap:15px}}
        @media(prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important;animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}.reveal{opacity:1;transform:none}}
    </style>
</head>
<body>
<header class="shell nav">
    <a class="brand" href="{{ route('home') }}" aria-label="Student Finance Manager home">
        <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 17.5 9 12l3.2 3.2L20 7.5M14.5 7.5H20v5.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        Student Finance
    </a>
    <nav class="nav-links" aria-label="Main navigation">
        <a href="#features">Features</a><a href="#preview">Preview</a><a href="#how-it-works">How it works</a>
    </nav>
    <div class="nav-actions">
        @auth
            <a class="btn btn-quiet" href="{{ route('dashboard') }}">Dashboard</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-primary" type="submit">Log out</button></form>
        @else
            <a class="btn btn-quiet" href="{{ route('login') }}">Sign in</a>
            <a class="btn btn-primary" href="{{ route('register') }}">Get started <span aria-hidden="true">↗</span></a>
        @endauth
    </div>
</header>
<main>
    <section class="shell hero" id="preview">
        <div class="hero-copy">
            <div class="pill"><span class="pill-dot"></span> Your money, in one clear view</div>
            <h1>Make your money <span>make sense.</span></h1>
            <p>Know what comes in, understand what goes out, and make confident decisions with a simpler way to manage your personal finances.</p>
            <div class="hero-actions">
                @auth
                    <a class="btn btn-primary" href="{{ route('dashboard') }}">Open your dashboard <span aria-hidden="true">→</span></a>
                @else
                    <a class="btn btn-primary" href="{{ route('register') }}">Create your free account <span aria-hidden="true">→</span></a>
                @endauth
                <a class="btn btn-outline" href="#features">Explore features</a>
            </div>
            <div class="trust"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg> Built to help you build better money habits</div>
        </div>
        <div class="visual" aria-label="Illustrative finance dashboard preview">
            <div class="orb"></div><span class="spark one"></span><span class="spark two"></span>
            <div class="float-card income"><div class="float-kicker"><span class="float-icon">↗</span> Income tracked</div><div class="float-value">KES 48,500</div><div class="float-note">A clearer view of earnings</div></div>
            <div class="dashboard">
                <div class="dash-top"><div><div class="dash-title">Your overview</div><div class="dash-sub">A snapshot of your finances · Preview</div></div><div class="avatar">SF</div></div>
                <div class="balance"><div class="balance-label">Available balance</div><div class="balance-amount">KES 32,840.00</div><div class="balance-foot">↗ 8.4% <span>illustrative monthly change</span></div></div>
                <div class="dash-stats"><div class="stat"><div class="stat-label">Total income</div><div class="stat-value">KES 48,500</div><div class="stat-meta">↑ Income overview</div></div><div class="stat"><div class="stat-label">Total expenses</div><div class="stat-value">KES 15,660</div><div class="stat-meta" style="color:#7770db">↘ Spending overview</div></div></div>
                <div class="chart-head"><span>Cash flow</span><span>Illustrative data</span></div>
                <svg class="chart" viewBox="0 0 440 106" preserveAspectRatio="none" role="img" aria-label="Illustrative upward cash flow chart">
                    <defs><linearGradient id="areaFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#7771f4" stop-opacity=".22"/><stop offset="1" stop-color="#7771f4" stop-opacity="0"/></linearGradient></defs>
                    <path class="gridline" d="M0 20H440M0 55H440M0 90H440"/><path class="area" d="M0 78 C28 73 36 58 66 64 S112 80 140 56 S185 47 211 55 S260 29 290 38 S332 48 356 25 S405 27 440 8 V106H0Z"/><path class="line" d="M0 78 C28 73 36 58 66 64 S112 80 140 56 S185 47 211 55 S260 29 290 38 S332 48 356 25 S405 27 440 8"/>
                </svg>
            </div>
            <div class="float-card savings"><div class="float-kicker"><span class="float-icon purple">◎</span> Savings goals</div><div class="float-value">Small steps add up</div><div class="float-note" style="color:#625bd7">Keep your goals in sight</div></div>
        </div>
    </section>
    <section class="section" id="features">
        <div class="shell">
            <div class="section-head reveal"><div class="eyebrow">Everything in one place</div><h2>A clearer picture of your finances.</h2><p>Simple tools to help you stay organised, spot patterns and make your next money decision with confidence.</p></div>
            <div class="features">
                <article class="feature reveal"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M4 18V6m0 12h16M7 14l4-4 3 3 5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h3>Track income</h3><p>Record earnings and see where your money is coming from, all in one organised view.</p></article>
                <article class="feature reveal"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm2 7h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div><h3>Understand spending</h3><p>Capture expenses, review transactions and identify the categories taking the biggest share.</p></article>
                <article class="feature reveal"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M4 19V5m0 14h16M8 15l3-3 3 2 5-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h3>Plan with budgets</h3><p>Set spending limits and keep an eye on progress before the month gets away from you.</p></article>
                <article class="feature reveal"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M12 3v18m5-14H9.5a3 3 0 0 0 0 6h5a3 3 0 0 1 0 6H6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div><h3>Build savings habits</h3><p>Keep savings goals visible and turn long-term plans into manageable milestones.</p></article>
                <article class="feature reveal"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M5 5h14v14H5zM8 9h8M8 12h8M8 15h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h3>Useful reports</h3><p>Review your financial activity with summaries that make your habits easier to understand.</p></article>
                <article class="feature reveal"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M12 3 5 6v5c0 4.5 2.9 8 7 10 4.1-2 7-5.5 7-10V6l-7-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h3>Your account, your space</h3><p>Sign in to a personal workspace designed to keep your financial records organised.</p></article>
            </div>
        </div>
    </section>
    <section class="band" id="how-it-works"><div class="shell band-inner"><div><h2>Start with a clearer view of your money.</h2><p>Create an account, add your transactions and build a routine that works for you.</p></div>@auth<a class="btn" href="{{ route('dashboard') }}">Go to dashboard <span aria-hidden="true">→</span></a>@else<a class="btn" href="{{ route('register') }}">Get started <span aria-hidden="true">→</span></a>@endauth</div></section>
</main>
<footer><div class="shell footer-row"><span>© {{ date('Y') }} Student Finance Manager. Built for better money habits.</span><div class="footer-links">@auth<a href="{{ route('dashboard') }}">Dashboard</a>@else<a href="{{ route('login') }}">Sign in</a><a href="{{ route('register') }}">Create account</a>@endauth</div></div></footer>
<script>
    (() => {
        const items = document.querySelectorAll('.reveal');
        if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            items.forEach(item => item.classList.add('visible'));
            return;
        }
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });
        items.forEach(item => observer.observe(item));
    })();
</script>
</body>
</html>
