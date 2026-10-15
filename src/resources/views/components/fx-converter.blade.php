@include('flutterwave::components.admin-styles')
@php($quote = $panel['quote'])
@php($trade = $panel['trade'])
@php($money = fn ($currency, $amount) => \Flutterwave\Payments\View\Components\FxConverter::money($currency, $amount))
<div id="{{ $componentId }}" {{ $attributes->merge(['class' => 'flw-admin flw-card']) }} style="max-width: 28rem">
    @if ($error = $firstError(['flutterwave', 'quantity', 'base', 'target', 'narration', 'quote_id']))
        <p class="flw-error" role="alert">{{ $error }}</p>
    @endif
    @if ($panel['error'])
        <p class="flw-error" role="alert">{{ $panel['error'] }}</p>
    @endif

    @if ($trade)
        <div aria-live="polite">
            <strong>{{ $trade['status'] === 'SETTLED' ? 'Trade settled' : ($trade['status'] === 'FAILED' ? 'Trade failed' : 'Trade in progress…') }}</strong>
            <p class="{{ $trade['status'] === 'FAILED' ? 'flw-error' : '' }}">
                @if ($trade['status'] === 'SETTLED')
                    Sold {{ $money($trade['base'], $trade['sold']) }} for {{ $money($trade['target'], $trade['bought']) }}{{ $trade['recipient'] ? ', paid to '.$trade['recipient'] : '' }}.
                @elseif ($trade['status'] === 'FAILED')
                    {{ $trade['message'] ?? 'Flutterwave couldn’t complete the trade.' }}
                @else
                    Selling {{ $money($trade['base'], $trade['sold']) }} for {{ $money($trade['target'], $trade['bought']) }}. This page refreshes by itself.
                @endif
            </p>
            <p class="flw-muted">Reference {{ $trade['reference'] ?? $trade['id'] }}</p>
        </div>
        @unless ($trade['pending'])
            <a class="flw-button" href="{{ $startOverUrl() }}">Start another conversion</a>
        @endunless
    @elseif ($quote && $quote['pending'])
        <div aria-live="polite">
            <strong>Getting a rate…</strong>
            <p class="flw-muted">Flutterwave usually prices a quote within a few seconds. This page refreshes by itself.</p>
        </div>
    @elseif ($quote && ! $quote['tradable'])
        <strong>{{ $quote['status'] === 'EXPIRED' ? 'This rate has expired' : 'No rate available' }}</strong>
        <p>{{ $quote['status'] === 'EXPIRED' ? 'Rates are held for 5 minutes. Get a new one to continue.' : $quote['message'] }}</p>
        <a class="flw-button" href="{{ $startOverUrl() }}">Get a new rate</a>
    @elseif ($quote)
        <div class="flw-row">
            <strong>Your rate</strong>
            <span class="flw-muted" data-flw-countdown="{{ $quote['expires_in'] }}">Held for {{ intdiv((int) $quote['expires_in'], 60) }}:{{ str_pad((string) ((int) $quote['expires_in'] % 60), 2, '0', STR_PAD_LEFT) }}</span>
        </div>
        <dl>
            <div><dt class="flw-muted">You sell</dt><dd>{{ $money($quote['base'], $quote['approved_quantity']) }}</dd></div>
            <div><dt class="flw-muted">You get</dt><dd>{{ $money($quote['target'], $quote['total_value']) }}</dd></div>
            <div style="grid-column: 1 / -1"><dt class="flw-muted">Rate</dt><dd>1 {{ $quote['base'] }} = {{ $quote['rate'] }} {{ $quote['target'] }}</dd></div>
        </dl>
        @if ($quote['quantity'] !== null && $quote['approved_quantity'] !== null && $quote['approved_quantity'] < $quote['quantity'])
            <p>Flutterwave approved {{ $money($quote['base'], $quote['approved_quantity']) }} of the {{ $money($quote['base'], $quote['quantity']) }} you asked for.</p>
        @endif
        @if ($panel['urls'])
            <form method="POST" action="{{ $panel['urls']['trade'] }}" data-flw-trade>
                @csrf
                <input type="hidden" name="quote_id" value="{{ $quote['id'] }}">
                <label>Note for your records
                    <input name="narration" required maxlength="255" placeholder="Supplier payment" value="{{ old('narration') }}">
                </label>
                <label class="flw-check"><input type="checkbox" required> Sell {{ $money($quote['base'], $quote['approved_quantity']) }} for {{ $money($quote['target'], $quote['total_value']) }}. Trades can’t be undone.</label>
                <button type="submit">Trade at this rate</button>
            </form>
            <a class="flw-muted" href="{{ $startOverUrl() }}">Get a different rate</a>
        @endif
        <p class="flw-error" data-flw-expired hidden>This rate has expired. <a href="{{ $startOverUrl() }}">Get a new one</a>.</p>
    @elseif (! $panel['urls'])
        <p class="flw-muted">Add Route::flutterwaveAdmin() to your routes to request quotes here.</p>
    @else
        <strong>Convert currency</strong>
        <p class="flw-muted">Get a rate, then trade it within 5 minutes. Weekdays only, from USD 1,000 or its equivalent.</p>
        <form method="POST" action="{{ $panel['urls']['quote'] }}" data-flw-quote>
            @csrf
            <input type="hidden" name="base" value="{{ $panel['pairs'][0][0] ?? 'NGN' }}">
            <input type="hidden" name="target" value="{{ $panel['pairs'][0][1] ?? 'USD' }}">
            <label>Convert
                <select data-flw-pair>
                    @foreach ($panel['pairs'] as [$from, $to])
                        <option value="{{ $from }}/{{ $to }}" @selected(old('base') === $from && old('target') === $to)>{{ $from }} to {{ $to }}</option>
                    @endforeach
                </select>
            </label>
            <label>Amount to sell
                <input name="quantity" required inputmode="decimal" placeholder="2000000" value="{{ old('quantity') }}">
            </label>
            <button type="submit">Get a rate</button>
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
                countdown.textContent = 'Held for ' + Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0');

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
