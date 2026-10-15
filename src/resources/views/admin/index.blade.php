<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payments · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light dark; --bg: #f6f7f9; --surface: #fff; --ink: #16181d; --muted: #5d6470; --line: #e3e5e9; --accent: #c2410c; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0e1013; --surface: #171a1f; --ink: #eceef2; --muted: #9aa2ae; --line: #2a2f37; --accent: #fb923c; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
        .page { max-width: 72rem; margin: 0 auto; padding: 32px max(16px, env(safe-area-inset-right)) 64px max(16px, env(safe-area-inset-left)); display: grid; gap: 28px; }
        header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 8px 24px; }
        h1 { margin: 0; font-size: 26px; letter-spacing: -.01em; }
        h2 { margin: 0 0 4px; font-size: 18px; }
        .muted { color: var(--muted); font-size: 14px; }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; display: grid; gap: 14px; min-width: 0; }
        .balances { display: flex; flex-wrap: wrap; gap: 12px 32px; }
        .balance strong { display: block; font-size: 22px; font-variant-numeric: tabular-nums; }
        .columns { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr); gap: 24px; align-items: start; }
        .tabs { display: flex; gap: 16px; font-size: 14px; }
        .tabs a { color: var(--muted); text-decoration: none; }
        .tabs a[aria-current] { color: var(--ink); font-weight: 600; }
        a { color: var(--accent); }
        @media (max-width: 52rem) { .columns { grid-template-columns: minmax(0, 1fr); } }
    </style>
</head>
<body>
<main class="page">
    <header>
        <div>
            <h1>Payments</h1>
            <p class="muted">Flutterwave balances, chargebacks and currency conversion for {{ config('app.name') }}.</p>
        </div>
        <span class="muted">{{ str_contains((string) config('flutterwave.secretKey'), '_TEST') ? 'Test mode' : 'Live mode' }}</span>
    </header>

    <section class="panel" aria-labelledby="balances-title">
        <h2 id="balances-title">Balances</h2>
        @if ($balancesError)
            <p class="muted">Couldn’t load balances: {{ $balancesError }}</p>
        @elseif ($balances === [])
            <p class="muted">No funds in any wallet yet.</p>
        @else
            <div class="balances">
                @foreach ($balances as $balance)
                    <div class="balance">
                        <span class="muted">{{ $balance['currency'] }}</span>
                        <strong>{{ number_format((float) $balance['available_balance'], 2) }}</strong>
                        @if ((float) $balance['ledger_balance'] !== (float) $balance['available_balance'])
                            <span class="muted">{{ number_format((float) $balance['ledger_balance'], 2) }} including pending</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <div class="columns">
        <section class="panel" aria-labelledby="chargebacks-title">
            <div>
                <h2 id="chargebacks-title">Chargebacks</h2>
                <nav class="tabs" aria-label="Chargeback filter">
                    <a href="{{ request()->fullUrlWithQuery(['chargebacks' => null]) }}" @if ($chargebackStatus === 'initiated') aria-current="page" @endif>Needs a response</a>
                    <a href="{{ request()->fullUrlWithQuery(['chargebacks' => 'all']) }}" @if ($chargebackStatus === 'all') aria-current="page" @endif>All</a>
                </nav>
            </div>
            <x-flutterwave-chargebacks :panel="$chargebacks" />
        </section>

        <section class="panel" aria-labelledby="fx-title">
            <div>
                <h2 id="fx-title">Convert currency</h2>
                <p class="muted">Trades settle into your Flutterwave balance.</p>
            </div>
            <x-flutterwave-fx :panel="$fx" />
        </section>
    </div>
</main>
</body>
</html>
