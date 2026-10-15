<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payments · {{ config('app.name') }}</title>
    @include('flutterwave::components.partials.styles')
    <style>
        body { margin: 0; background: #fff; }
        .flw-page { gap: 0; min-height: 100vh; }
        .flw-page__crumb { border-bottom: 1px solid var(--flw-line); }
        .flw-page__crumb-inner, .flw-page__body { margin: 0 auto; max-width: 1560px; padding-left: max(16px, min(48px, 4vw)); padding-right: max(16px, min(48px, 4vw)); width: 100%; }
        .flw-page__crumb-inner { align-items: center; display: flex; gap: 16px; justify-content: space-between; min-height: 64px; }
        .flw-page__body { display: grid; gap: 28px; padding-bottom: 80px; padding-top: 40px; }
        .flw-wordmark { display: block; height: 28px; width: 176px; }
        .flw-columns { align-items: start; display: grid; gap: 40px; grid-template-columns: minmax(0, 774fr) minmax(0, 500fr); }
        .flw-balances { display: flex; flex-wrap: wrap; gap: 20px 48px; }
        @media (max-width: 1100px) { .flw-columns { grid-template-columns: minmax(0, 1fr); } }
    </style>
</head>
<body>
<div class="flw flw-page">
    <div class="flw-page__crumb">
        <div class="flw-page__crumb-inner">
            <svg class="flw-wordmark" viewBox="0 0 200 32" role="img" aria-label="Flutterwave">
                <path d="M14.8 4.2c4.6-2.6 10.2-.9 12.6 3.7 2.3 4.6.5 10.2-4 12.7l-9.9 5.5c-4.6 2.6-10.2.9-12.6-3.7C-1.4 17.8.4 12.2 5 9.7l9.8-5.5Z" fill="#F5A623"/>
                <path d="M5.2 4.2C9.8 1.6 15.4 3.3 17.8 7.9c2.3 4.6.5 10.2-4 12.7-4.6 2.6-10.2.9-12.6-3.7C-1.1 12.3.7 6.7 5.2 4.2Z" fill="#F04E23" opacity=".85"/>
                <text x="40" y="23" fill="currentColor" font-family="Millik, Georgia, serif" font-size="22">flutterwave</text>
            </svg>
            <span class="flw-pill" data-tone="{{ str_contains((string) config('flutterwave.secretKey'), '_TEST') ? 'attention' : 'good' }}">{{ str_contains((string) config('flutterwave.secretKey'), '_TEST') ? 'Test mode' : 'Live mode' }}</span>
        </div>
    </div>

    <main class="flw-page__body">
        <div class="flw-stack">
            <h1 class="flw-heading">Payments</h1>
            <p class="flw-text">Balances, chargebacks, transactions and payouts for {{ config('app.name') }}.</p>
        </div>

        <nav class="flw-tabs" aria-label="Payments sections">
            @foreach ($sections as $key => $label)
                <a class="flw-tab" href="{{ request()->url() }}{{ $key === 'overview' ? '' : '?section='.$key }}" @if ($section === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        @if ($section === 'transactions')
            <section class="flw-stack" style="gap: 20px" aria-labelledby="section-title">
                <div class="flw-stack"><h2 class="flw-title" id="section-title">Transactions</h2><p class="flw-text">Payments from the last 30 days unless you pick dates. Refund one, see its timeline, or send its webhook again.</p></div>
                <x-flutterwave-transactions :panel="$transactions" />
            </section>
        @elseif ($section === 'refunds')
            <section class="flw-stack" style="gap: 20px" aria-labelledby="section-title">
                <div class="flw-stack"><h2 class="flw-title" id="section-title">Refunds</h2><p class="flw-text">Money returned to customers. Start a refund from the transaction.</p></div>
                <x-flutterwave-refunds :panel="$refunds" />
            </section>
        @elseif ($section === 'settlements')
            <section class="flw-stack" style="gap: 20px" aria-labelledby="section-title">
                <div class="flw-stack"><h2 class="flw-title" id="section-title">Settlements</h2><p class="flw-text">Payouts of the money you collected, after Flutterwave’s fees.</p></div>
                <x-flutterwave-settlements :panel="$settlements" />
            </section>
        @elseif ($section === 'beneficiaries')
            <section class="flw-stack" style="gap: 20px" aria-labelledby="section-title">
                <div class="flw-stack"><h2 class="flw-title" id="section-title">Beneficiaries</h2><p class="flw-text">Saved bank accounts for transfers.</p></div>
                <x-flutterwave-beneficiaries :panel="$beneficiaries" />
            </section>
        @else
            <section class="flw-card" aria-labelledby="balances-title">
                <h2 class="flw-title" id="balances-title">Balances</h2>
                @if ($balancesError)
                    <p class="flw-notice flw-notice--error">Couldn’t load balances: {{ $balancesError }}</p>
                @elseif ($balances === [])
                    <p class="flw-text">No funds in any wallet yet.</p>
                @else
                    <div class="flw-balances">
                        @foreach ($balances as $balance)
                            <div class="flw-stack">
                                <span class="flw-muted">{{ $balance['currency'] }}</span>
                                <span class="flw-figure">{{ number_format((float) $balance['available_balance'], 2) }}</span>
                                @if ((float) $balance['ledger_balance'] !== (float) $balance['available_balance'])
                                    <span class="flw-muted">{{ number_format((float) $balance['ledger_balance'], 2) }} including pending</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="flw-columns">
                <section class="flw-stack" style="gap: 16px" aria-labelledby="chargebacks-title">
                    <div class="flw-stack">
                        <h2 class="flw-title" id="chargebacks-title">Chargebacks</h2>
                        <nav class="flw-tabs" aria-label="Chargeback filter">
                            <a class="flw-tab" href="{{ request()->fullUrlWithQuery(['chargebacks' => null]) }}" @if ($chargebackStatus === 'initiated') aria-current="page" @endif>Needs a response</a>
                            <a class="flw-tab" href="{{ request()->fullUrlWithQuery(['chargebacks' => 'all']) }}" @if ($chargebackStatus === 'all') aria-current="page" @endif>All</a>
                        </nav>
                    </div>
                    <x-flutterwave-chargebacks :panel="$chargebacks" />
                </section>

                <section class="flw-stack" style="gap: 16px" aria-labelledby="fx-title">
                    <div class="flw-stack">
                        <h2 class="flw-title" id="fx-title">Convert currency</h2>
                        <p class="flw-text">Trades settle into your Flutterwave balance.</p>
                    </div>
                    <x-flutterwave-fx :panel="$fx" />
                </section>
            </div>
        @endif
    </main>
</div>
</body>
</html>
