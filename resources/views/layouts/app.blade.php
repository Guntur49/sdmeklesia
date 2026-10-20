<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'EKLESIA GROUP' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{--ink:#13221d;--muted:#6d7d75;--paper:#f5f7f2;--card:#fff;--line:#dce6df;--green:#1b7a58;--mint:#dff2e8;--coral:#e36b55;--gold:#eabf64;--shadow:0 18px 50px #16382b12}*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 90% 5%,#e4f2e7 0,transparent 34%),var(--paper);color:var(--ink);font:15px 'DM Sans',sans-serif}h1,h2,h3{font-family:'Space Grotesk',sans-serif;margin:0 0 10px;letter-spacing:0}a{color:inherit;text-decoration:none}.shell{min-height:100vh;display:flex}.sidebar{width:250px;background:var(--ink);color:#fff;padding:28px 20px;display:flex;flex-direction:column}.brand{font:700 20px 'Space Grotesk';letter-spacing:.04em}.brand small{display:block;color:#9bbbad;font:11px 'DM Sans';margin-top:5px}.nav{margin-top:54px;display:grid;gap:8px}.nav a{padding:13px 14px;border-radius:10px;color:#b9ccc2}.nav a:hover,.nav a.active{background:#244b3d;color:#fff}.sidebar form{margin-top:auto}.main{flex:1;padding:34px clamp(20px,4vw,60px);max-width:1440px}.topbar{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:32px}.eyebrow{font-size:12px;text-transform:uppercase;letter-spacing:.12em;color:var(--green);font-weight:700}.muted{color:var(--muted)}.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:24px;box-shadow:var(--shadow)}.grid{display:grid;gap:18px}.grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}.grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}.stat{min-height:130px}.stat b{display:block;font:700 32px 'Space Grotesk';margin:14px 0 5px}.btn{border:0;border-radius:9px;padding:12px 16px;background:var(--green);color:#fff;font:600 14px 'DM Sans';cursor:pointer}.btn:hover{filter:brightness(.93)}.btn.ghost{background:#eaf2ec;color:var(--green)}.btn.danger{background:#fbe7e3;color:#b84d3b}.btn.dark{background:var(--ink)}input,select,textarea{width:100%;border:1px solid var(--line);border-radius:8px;padding:11px 12px;background:#fbfdfb;color:var(--ink);font:inherit}label{display:grid;gap:7px;font-weight:600;font-size:13px}.form-grid{display:grid;gap:14px}.form-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.checkdays{display:flex;flex-wrap:wrap;gap:8px}.checkdays label{display:flex;align-items:center;gap:5px;background:#f1f6f2;padding:8px 10px;border-radius:7px;font-weight:500}.checkdays input{width:auto}.alert{padding:13px 16px;border-radius:10px;margin-bottom:18px}.alert.success{background:#e1f4e9;color:#17623f}.alert.error{background:#fce8e3;color:#a74234}.camera{background:#101c18;border-radius:12px;overflow:hidden;position:relative;min-height:280px;display:grid;place-items:center}.camera video{display:block;width:100%;height:100%;max-height:390px;object-fit:cover;transform:scaleX(-1)}.camera .camera-note{position:absolute;bottom:12px;left:12px;right:12px;color:#fff;background:#07130dcc;border-radius:7px;padding:9px;font-size:12px}.pill{display:inline-flex;padding:6px 10px;border-radius:99px;background:var(--mint);color:var(--green);font-size:12px;font-weight:700}.table-wrap{overflow:auto}.table{width:100%;border-collapse:collapse}.table th,.table td{text-align:left;padding:13px 10px;border-bottom:1px solid var(--line);white-space:nowrap}.table th{color:var(--muted);font-size:12px;text-transform:uppercase}.calendar{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}.day{min-height:72px;border-radius:9px;padding:8px;background:#f1f5f1;border:1px solid transparent}.day.present{background:#dff2e8;border-color:#b1dfc7}.day.absent{background:#fde8e3;border-color:#f5c7bc}.day.leave{background:#fff4d8;border-color:#f4dda1}.day b{display:block;font-size:13px}.day small{font-size:10px;color:var(--muted)}.mobile-only{display:none}.login{min-height:100vh;display:grid;grid-template-columns:1.1fr .9fr;background:var(--ink)}.login-art{padding:clamp(30px,7vw,100px);color:#fff;background:linear-gradient(145deg,#173d30,#1b7a58);position:relative;overflow:hidden}.login-art:after{content:'';position:absolute;width:480px;height:480px;border:1px solid #ffffff35;border-radius:50%;right:-180px;bottom:-130px;box-shadow:0 0 0 50px #ffffff0c,0 0 0 100px #ffffff08}.login-art h1{font-size:clamp(42px,6vw,78px);line-height:.95;max-width:560px;margin-top:30px}.login-art p{max-width:390px;color:#c8e1d4;line-height:1.7}.login-panel{background:var(--paper);display:grid;place-items:center;padding:25px}.login-card{width:min(420px,100%)}.login-card h2{font-size:32px}.logout{background:transparent;border:0;color:#aac5b8;padding:12px 14px;cursor:pointer;font:inherit;text-align:left}
        @media(max-width:850px){.shell{display:block}.sidebar{width:auto;padding:18px 20px}.nav{margin:18px 0 0;display:flex;overflow:auto}.nav a{white-space:nowrap;padding:9px 12px}.sidebar form{position:absolute;right:14px;top:13px}.logout{padding:8px;color:#fff}.main{padding:24px 16px}.grid-2,.grid-3{grid-template-columns:1fr}.topbar{align-items:flex-start}.mobile-only{display:block}.login{grid-template-columns:1fr}.login-art{min-height:300px;padding:36px 24px}.login-art h1{font-size:48px}.login-panel{padding:28px 18px}.form-row{grid-template-columns:1fr}.card{padding:18px}.calendar{gap:3px}.day{min-height:54px;padding:5px}.day small{display:none}}
    </style>
    <style>
        :root {
            --ink: #54250f;
            --muted: #896b5a;
            --paper: #fff7f0;
            --card: #fffdfa;
            --line: #f2d8c2;
            --green: #c95a1d;
            --mint: #fff1e3;
            --coral: #e36b55;
            --gold: #f59e0b;
            --shadow: 0 18px 50px rgba(98, 43, 15, .08);
        }

        body {
            background: radial-gradient(circle at 90% 5%, #ffead6 0, transparent 34%), var(--paper);
        }

        .sidebar {
            background: linear-gradient(180deg, #7c2d12, #54250f);
        }

        .brand small,
        .logout {
            color: #ffcf9f;
        }

        .nav a {
            color: #ffe2c4;
        }

        .nav a:hover,
        .nav a.active {
            background: #a84316;
            color: #fff;
        }

        .btn {
            background: linear-gradient(135deg, #f97316, #ea580c);
            box-shadow: 0 8px 18px rgba(249, 115, 22, .2);
        }

        .btn.ghost {
            background: #fff1e3;
            color: #a84316;
            box-shadow: none;
        }

        .btn.dark {
            background: #7c2d12;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #f97316;
            outline: 2px solid rgba(249, 115, 22, .18);
        }

        input[type="checkbox"],
        input[type="radio"] {
            accent-color: #ea580c;
        }

        .checkdays label,
        .day {
            background: #fff7f0;
        }

        .alert.success {
            background: #fff1e3;
            color: #8a3412;
        }

        .camera {
            background: #54250f;
        }

        .camera .camera-note {
            background: rgba(84, 37, 15, .88);
        }

        .day.present {
            background: #ffead6;
            border-color: #f4c59f;
        }

        .day.leave {
            background: #fff5df;
            border-color: #f4dda1;
        }
    </style>
    <style>
        @media (max-width: 850px) {
            .shell,
            .main {
                min-width: 0;
            }

            .sidebar {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: center;
                gap: 12px 16px;
                padding: 16px;
            }

            .brand {
                min-width: 0;
                font-size: 18px;
            }

            .sidebar .nav {
                grid-column: 1 / -1;
                width: 100%;
                min-width: 0;
                margin: 0;
                padding-bottom: 2px;
                overscroll-behavior-x: contain;
                scrollbar-width: none;
                -webkit-overflow-scrolling: touch;
            }

            .sidebar .nav::-webkit-scrollbar {
                display: none;
            }

            .sidebar .nav a {
                flex: 0 0 auto;
            }

            .sidebar form {
                position: static;
                margin: 0;
            }

            .logout {
                padding: 10px;
                color: #ffcf9f;
                white-space: nowrap;
            }

            .main {
                padding: 22px 14px;
            }

            .topbar {
                flex-wrap: wrap;
                align-items: flex-start;
                gap: 10px;
                margin-bottom: 20px;
            }

            .topbar > div {
                min-width: 0;
            }

            .topbar h1 {
                font-size: clamp(26px, 7vw, 34px);
                overflow-wrap: anywhere;
            }

            .topbar > .pill {
                margin-left: auto;
            }

            .main > .grid > .grid > .grid-2 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .card {
                min-width: 0;
                padding: 16px;
                border-radius: 12px;
            }

            .stat {
                padding: 14px;
            }

            .stat b {
                font-size: clamp(23px, 7vw, 30px);
            }

            .camera {
                min-height: 0;
                aspect-ratio: 4 / 3;
            }

            .camera video {
                height: 100%;
                max-height: none;
            }

            .camera .camera-note {
                right: 8px;
                bottom: 8px;
                left: 8px;
            }

            input,
            select,
            textarea {
                font-size: 16px;
            }

            .btn {
                min-height: 44px;
            }
        }

        @media (max-width: 380px) {
            .sidebar {
                gap: 10px;
                padding: 14px 12px;
            }

            .brand {
                font-size: 16px;
            }

            .main {
                padding-right: 12px;
                padding-left: 12px;
            }

            .card {
                padding: 14px;
            }
        }

        .currency-field {
            display: flex;
            width: 100%;
            min-width: 0;
        }

        .currency-prefix {
            display: flex;
            align-items: center;
            padding: 0 12px;
            border: 1px solid var(--line);
            border-right: 0;
            border-radius: 8px 0 0 8px;
            background: #fff1e3;
            color: #a84316;
            font-weight: 600;
        }

        .currency-field input {
            min-width: 0;
            border-radius: 0 8px 8px 0;
            text-align: right;
        }
    </style>
    @stack('styles')
</head>
<body>
@if(auth()->check())
<div class="shell"><aside class="sidebar"><a class="brand" href="{{ auth()->user()->isAdmin() ? route('admin') : route('staff') }}">EKLESIA GROUP<small>STAFF ATTENDANCE SYSTEM</small></a><nav class="nav">@if(auth()->user()->isAdmin())<a class="{{ request()->routeIs('admin') ? 'active' : '' }}" href="{{ route('admin') }}">Panel admin</a><a class="{{ request()->routeIs('admin.staff*') ? 'active' : '' }}" href="{{ route('admin.staff') }}">Rincian staff</a><a class="{{ request()->routeIs('admin.report*') ? 'active' : '' }}" href="{{ route('admin.report') }}">Ringkasan laporan</a><a class="{{ request()->routeIs('admin.payroll-slips.*') ? 'active' : '' }}" href="{{ route('admin.payroll-slips.index') }}">Gaji staff</a>@else<a class="{{ request()->routeIs('staff') ? 'active' : '' }}" href="{{ route('staff') }}">Absensi saya</a>@endif</nav><form method="post" action="{{ route('logout') }}">@csrf<button class="logout" type="submit">Keluar</button></form></aside><main class="main">@if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif @if(session('error'))<div class="alert error">{{ session('error') }}</div>@endif @yield('content')</main></div>
@else @yield('content') @endif
<script>
    const currencyInputNames = [
        'base_salary',
        'daily_attendance_allowance',
        'daily_meal_allowance',
        'daily_transport_allowance',
        'bpjs_subsidy',
        'attendance',
        'umkm',
        'transport',
        'bpjs',
        'other_honor',
        'position_allowance',
        'overtime',
        'holiday',
        'thr',
        'bonus',
        'deductions',
        'lateness',
        'bpjs_deduction',
        'cooperative_deduction',
        'sanction',
        'honor_received',
    ];

    function formatCurrencyInput(input) {
        const cursor = input.selectionStart ?? input.value.length;
        const digitsBeforeCursor = input.value.slice(0, cursor).replace(/\D/g, '').length;
        let digits = input.value.replace(/\D/g, '').slice(0, 15).replace(/^0+(?=\d)/, '');

        if (!digits) {
            digits = '0';
        }

        input.value = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        let digitCount = 0;
        let nextCursor = digitsBeforeCursor === 0 ? 0 : input.value.length;

        for (let index = 0; index < input.value.length; index++) {
            if (/\d/.test(input.value[index])) {
                digitCount++;
            }

            if (digitCount === digitsBeforeCursor) {
                nextCursor = index + 1;
                break;
            }
        }

        input.setSelectionRange(nextCursor, nextCursor);
    }

    for (const name of currencyInputNames) {
        for (const input of document.querySelectorAll(`input[name="${name}"]`)) {
            const prefix = document.createElement('span');
            const wrapper = document.createElement('div');

            prefix.className = 'currency-prefix';
            prefix.textContent = 'Rp';
            prefix.setAttribute('aria-hidden', 'true');
            wrapper.className = 'currency-field';

            input.type = 'text';
            input.inputMode = 'numeric';
            input.maxLength = 19;
            input.autocomplete = 'off';
            input.setAttribute('aria-label', `${name.replaceAll('_', ' ')} dalam rupiah`);
            input.parentNode.insertBefore(wrapper, input);
            wrapper.append(prefix, input);
            formatCurrencyInput(input);

            input.addEventListener('focus', () => {
                if (input.value === '0') {
                    input.select();
                }
            });
            input.addEventListener('input', () => formatCurrencyInput(input));
            input.form?.addEventListener('submit', () => {
                input.value = input.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '') || '0';
            });
        }
    }
</script>
</body></html>