@include('flutterwave::components.partials.styles')
@php($filters = $panel['filters'])
<div {{ $attributes->merge(['class' => 'flw']) }}>
    @if (session('flutterwave.status'))
        <p class="flw-notice flw-notice--success" role="status">{{ session('flutterwave.status') }}</p>
    @endif
    @if ($error = $firstError(['flutterwave', 'amount', 'comment']))
        <p class="flw-notice flw-notice--error" role="alert">{{ $error }}</p>
    @endif

    <form method="GET" class="flw-filters">
        @include('flutterwave::components.keep-query', ['except' => ['from', 'to', 'status', 'customer_email', 'tx_ref', 'transaction']])
        <label class="flw-field"><span class="flw-field__label">From</span><input class="flw-input flw-input--sm" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label class="flw-field"><span class="flw-field__label">To</span><input class="flw-input flw-input--sm" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <label class="flw-field">
            <span class="flw-field__label">Status</span>
            <span class="flw-select">
                <select class="flw-input flw-input--sm" name="status">
                    <option value="">Any</option>
                    @foreach (['successful' => 'Successful', 'failed' => 'Failed', 'pending' => 'Pending'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @include('flutterwave::components.partials.chevron')
            </span>
        </label>
        <label class="flw-field"><span class="flw-field__label">Customer email</span><input class="flw-input flw-input--sm" type="email" name="customer_email" value="{{ $filters['customer_email'] ?? '' }}" placeholder="jane@example.com"></label>
        <label class="flw-field"><span class="flw-field__label">Reference</span><input class="flw-input flw-input--sm" name="tx_ref" value="{{ $filters['tx_ref'] ?? '' }}" placeholder="ORDER-42"></label>
        <button type="submit" class="flw-btn flw-btn--primary flw-btn--sm">Show</button>
    </form>

    @if ($panel['error'])
        <p class="flw-notice flw-notice--error">Couldn’t load transactions: {{ $panel['error'] }}</p>
    @elseif ($panel['items'] === [])
        <p class="flw-text">No transactions match these filters.</p>
    @else
        <ul class="flw-rows">
            @foreach ($panel['items'] as $transaction)
                @php($open = ($panel['timeline']['transaction_id'] ?? null) === (string) $transaction['id'])
                <li>
                    <div class="flw-row">
                        <div class="flw-stack">
                            <span class="flw-amount">{{ $money($transaction['currency'], $transaction['amount']) }}</span>
                            <span class="flw-meta">
                                <span>{{ $transaction['customer_name'] ?: $transaction['customer_email'] }}</span>
                                @if ($transaction['customer_name'] && $transaction['customer_email'])<span>{{ $transaction['customer_email'] }}</span>@endif
                                <span>{{ $transaction['tx_ref'] }}</span>
                                <span>{{ $date($transaction['created_at']) }}</span>
                                @if ($transaction['payment_type'])<span>{{ str_replace('_', ' ', $transaction['payment_type']) }}</span>@endif
                            </span>
                        </div>
                        <span class="flw-pill" data-tone="{{ $tone($transaction['status']) }}">{{ ucfirst($transaction['status']) }}</span>
                    </div>
                    <div class="flw-actions">
                        <a class="flw-link-btn" href="{{ $urlWith(['transaction' => $open ? null : $transaction['id']]) }}">{{ $open ? 'Hide timeline' : 'Timeline' }}</a>
                        @if ($transaction['urls'])
                            <form method="POST" action="{{ $transaction['urls']['resend_webhook'] }}">
                                @csrf
                                <button type="submit" class="flw-btn flw-btn--outline flw-btn--sm">Resend webhook</button>
                            </form>
                        @endif
                    </div>
                    @if ($transaction['urls']['refund'] ?? null)
                        <details class="flw-details">
                            <summary>Refund</summary>
                            <form method="POST" action="{{ $transaction['urls']['refund'] }}" class="flw-panel">
                                @csrf
                                <label class="flw-field">
                                    <span class="flw-field__label">Amount to refund</span>
                                    <input class="flw-input" name="amount" inputmode="decimal" placeholder="Leave empty to refund {{ $money($transaction['currency'], $transaction['amount']) }} in full">
                                </label>
                                <label class="flw-field"><span class="flw-field__label">Reason</span><input class="flw-input" name="comment" maxlength="255" placeholder="Damaged on delivery"></label>
                                <label class="flw-checkbox"><input type="checkbox" required>@include('flutterwave::components.partials.check')<span>Refunds can’t be undone. The money goes back to how the customer paid.</span></label>
                                <button type="submit" class="flw-btn flw-btn--primary">Refund</button>
                            </form>
                        </details>
                    @endif
                    @if ($open)
                        @if ($panel['timeline']['error'])
                            <p class="flw-error-text">{{ $panel['timeline']['error'] }}</p>
                        @else
                            <ol class="flw-timeline">
                                @foreach ($panel['timeline']['events'] as $event)
                                    <li>{{ $event['note'] ?? $event['action'] }} <span class="flw-muted">{{ $date($event['created_at'] ?? null) }}</span></li>
                                @endforeach
                            </ol>
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @include('flutterwave::components.pager', ['page' => $panel['page'], 'urlWith' => $urlWith])

    <details class="flw-details">
        <summary>Check a fee</summary>
        <form method="GET" class="flw-filters">
            @include('flutterwave::components.keep-query', ['except' => ['fee_amount', 'fee_currency', 'fee_type']])
            <label class="flw-field"><span class="flw-field__label">Amount</span><input class="flw-input flw-input--sm" name="fee_amount" required inputmode="decimal" value="{{ request('fee_amount') }}" placeholder="10000"></label>
            <label class="flw-field"><span class="flw-field__label">Currency</span><input class="flw-input flw-input--sm" name="fee_currency" required maxlength="3" value="{{ request('fee_currency', config('flutterwave.currency', 'NGN')) }}"></label>
            <label class="flw-field">
                <span class="flw-field__label">Paid by</span>
                <span class="flw-select">
                    <select class="flw-input flw-input--sm" name="fee_type">
                        <option value="">Any method</option>
                        @foreach (['card' => 'Card', 'debit_ng_account' => 'Bank account', 'mobilemoney' => 'Mobile money', 'bank_transfer' => 'Bank transfer'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('fee_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @include('flutterwave::components.partials.chevron')
                </span>
            </label>
            <button type="submit" class="flw-btn flw-btn--outline flw-btn--sm">Check</button>
        </form>
        @if ($panel['fee'])
            @if ($panel['fee']['error'])
                <p class="flw-error-text">{{ $panel['fee']['error'] }}</p>
            @else
                {{-- Built here because Blade ignores an @endif straight after a word. --}}
                @php($stampDuty = empty($panel['fee']['stamp_duty_fee']) ? '' : ', including '.$money($panel['fee']['currency'] ?? null, $panel['fee']['stamp_duty_fee']).' stamp duty')
                <p class="flw-text">Flutterwave’s fee is <strong>{{ $money($panel['fee']['currency'] ?? null, $panel['fee']['fee'] ?? null) }}</strong>, so the customer is charged {{ $money($panel['fee']['currency'] ?? null, $panel['fee']['charge_amount'] ?? null) }}{{ $stampDuty }}.</p>
            @endif
        @endif
    </details>
</div>
