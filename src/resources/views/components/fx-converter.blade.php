@include('flutterwave::components.partials.styles')
@php($quote = $panel['quote'])
@php($trade = $panel['trade'])
@php($money = fn ($currency, $amount) => \Flutterwave\Payments\View\Components\FxConverter::money($currency, $amount))
<div id="{{ $componentId }}" {{ $attributes->merge(['class' => 'flw flw-card flw-card--narrow']) }}>
    @if ($error = $firstError(['flutterwave', 'quantity', 'base', 'target', 'narration', 'quote_id']))
        <p class="flw-notice flw-notice--error" role="alert">{{ $error }}</p>
    @endif
    @if ($panel['error'])
        <p class="flw-notice flw-notice--error" role="alert">{{ $panel['error'] }}</p>
    @endif

    @if ($trade)
        <div class="flw-stack" aria-live="polite" style="gap: 10px">
            <h3 class="flw-title">{{ $trade['status'] === 'SETTLED' ? 'Trade settled' : ($trade['status'] === 'FAILED' ? 'Trade failed' : 'Trade in progress') }}</h3>
            <hr class="flw-rule">
            @if ($trade['status'] === 'SETTLED')
                <p class="flw-text">Sold {{ $money($trade['base'], $trade['sold']) }} for <strong>{{ $money($trade['target'], $trade['bought']) }}</strong>{{ $trade['recipient'] ? ', paid to '.$trade['recipient'] : '' }}.</p>
            @elseif ($trade['status'] === 'FAILED')
                <p class="flw-notice flw-notice--error">{{ $trade['message'] ?? 'Flutterwave couldn’t complete the trade.' }}</p>
            @else
                <p class="flw-countdown"><span class="flw-ring" aria-hidden="true"></span>Selling {{ $money($trade['base'], $trade['sold']) }} for {{ $money($trade['target'], $trade['bought']) }}. This page refreshes by itself.</p>
            @endif
            <p class="flw-muted">Reference {{ $trade['reference'] ?? $trade['id'] }}</p>
        </div>
        @unless ($trade['pending'])
            <a class="flw-btn flw-btn--primary flw-btn--block" href="{{ $startOverUrl() }}">Start another conversion</a>
        @endunless
    @elseif ($quote && $quote['pending'])
        <div class="flw-stack" aria-live="polite" style="gap: 10px">
            <h3 class="flw-title">Getting a rate</h3>
            <hr class="flw-rule">
            <p class="flw-countdown"><span class="flw-ring" aria-hidden="true"></span>Flutterwave usually prices a quote within a few seconds. This page refreshes by itself.</p>
        </div>
    @elseif ($quote && ! $quote['tradable'])
        <div class="flw-stack" style="gap: 10px">
            <h3 class="flw-title">{{ $quote['status'] === 'EXPIRED' ? 'This rate has expired' : 'No rate available' }}</h3>
            <hr class="flw-rule">
            <p class="flw-text">{{ $quote['status'] === 'EXPIRED' ? 'Rates are held for 5 minutes. Get a new one to continue.' : $quote['message'] }}</p>
        </div>
        <a class="flw-btn flw-btn--primary flw-btn--block" href="{{ $startOverUrl() }}">Get a new rate</a>
    @elseif ($quote)
        <div class="flw-row">
            <h3 class="flw-title">Your rate</h3>
            <p class="flw-countdown">Held for <strong data-flw-countdown="{{ $quote['expires_in'] }}">{{ intdiv((int) $quote['expires_in'], 60) }}:{{ str_pad((string) ((int) $quote['expires_in'] % 60), 2, '0', STR_PAD_LEFT) }}</strong></p>
        </div>
        <hr class="flw-rule">
        <dl class="flw-dl">
            <div><dt>You sell</dt><dd>{{ $money($quote['base'], $quote['approved_quantity']) }}</dd></div>
            <div><dt>You get</dt><dd>{{ $money($quote['target'], $quote['total_value']) }}</dd></div>
            <div><dt>Rate</dt><dd>1 {{ $quote['base'] }} = {{ $quote['rate'] }} {{ $quote['target'] }}</dd></div>
        </dl>
        @if ($quote['quantity'] !== null && $quote['approved_quantity'] !== null && $quote['approved_quantity'] < $quote['quantity'])
            <p class="flw-notice flw-notice--warning">Flutterwave approved {{ $money($quote['base'], $quote['approved_quantity']) }} of the {{ $money($quote['base'], $quote['quantity']) }} you asked for.</p>
        @endif
        @if ($panel['urls'])
            <form method="POST" action="{{ $panel['urls']['trade'] }}" data-flw-trade class="flw-stack" style="gap: 16px">
                @csrf
                <input type="hidden" name="quote_id" value="{{ $quote['id'] }}">
                <label class="flw-field">
                    <span class="flw-field__label">Note for your records</span>
                    <input class="flw-input" name="narration" required maxlength="255" placeholder="Supplier payment" value="{{ old('narration') }}">
                </label>
                <label class="flw-checkbox"><input type="checkbox" required>@include('flutterwave::components.partials.check')<span>Sell {{ $money($quote['base'], $quote['approved_quantity']) }} for {{ $money($quote['target'], $quote['total_value']) }}. Trades can’t be undone.</span></label>
                <button type="submit" class="flw-btn flw-btn--primary flw-btn--block">Trade at this rate</button>
            </form>
            <a class="flw-link-btn flw-link-btn--quiet" href="{{ $startOverUrl() }}">Get a different rate</a>
        @endif
        <p class="flw-notice flw-notice--error" data-flw-expired hidden>This rate has expired. <a href="{{ $startOverUrl() }}">Get a new one</a>.</p>
    @elseif (! $panel['urls'])
        <p class="flw-text">Add Route::flutterwaveAdmin() to your routes to request quotes here.</p>
    @else
        <div class="flw-stack">
            <h3 class="flw-title">Convert currency</h3>
            <p class="flw-text">Get a rate, then trade it within 5 minutes. Weekdays only, from USD 1,000 or its equivalent.</p>
        </div>
        <hr class="flw-rule">
        <form method="POST" action="{{ $panel['urls']['quote'] }}" data-flw-quote class="flw-stack" style="gap: 16px">
            @csrf
            <input type="hidden" name="base" value="{{ $panel['pairs'][0][0] ?? 'NGN' }}">
            <input type="hidden" name="target" value="{{ $panel['pairs'][0][1] ?? 'USD' }}">
            <label class="flw-field">
                <span class="flw-field__label">Convert</span>
                <span class="flw-select">
                    <select class="flw-input" data-flw-pair>
                        @foreach ($panel['pairs'] as [$from, $to])
                            <option value="{{ $from }}/{{ $to }}" @selected(old('base') === $from && old('target') === $to)>{{ $from }} to {{ $to }}</option>
                        @endforeach
                    </select>
                    @include('flutterwave::components.partials.chevron')
                </span>
            </label>
            <label class="flw-field">
                <span class="flw-field__label">Amount to sell</span>
                <input class="flw-input" name="quantity" required inputmode="decimal" placeholder="2000000" value="{{ old('quantity') }}">
            </label>
            <button type="submit" class="flw-btn flw-btn--primary flw-btn--block">Get a rate</button>
        </form>
    @endif
</div>
<script>
    (function () {
        var root = document.getElementById(@json($componentId));
        var pair = root.querySelector('[data-flw-pair]');

        if (pair) {
            var sync = function () {
                var parts = pair.value.split('/');
                root.querySelector('[name=base]').value = parts[0];
                root.querySelector('[name=target]').value = parts[1];
            };
            pair.addEventListener('change', sync);
            sync();
        }

        var countdown = root.querySelector('[data-flw-countdown]');

        if (countdown) {
            // Counted from the seconds left at render time, so the browser's clock doesn't matter.
            var deadline = Date.now() + parseInt(countdown.getAttribute('data-flw-countdown'), 10) * 1000;
            (function tick() {
                var left = Math.max(0, Math.round((deadline - Date.now()) / 1000));
                countdown.textContent = Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0');

                if (left === 0) {
                    var form = root.querySelector('[data-flw-trade]');
                    if (form) form.hidden = true;
                    root.querySelector('[data-flw-expired]').hidden = false;
                    return;
                }

                setTimeout(tick, 1000);
            })();
        }

        @if ($waiting())
            setTimeout(function () { window.location.reload(); }, {{ max(1, $refreshSeconds) * 1000 }});
        @endif
    })();
</script>
