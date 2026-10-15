@include('flutterwave::components.partials.styles')
@php($filters = $panel['filters'])
@php($selected = $panel['selected'])
<div {{ $attributes->merge(['class' => 'flw']) }}>
    <form method="GET" class="flw-filters">
        @include('flutterwave::components.keep-query', ['except' => ['from', 'to', 'settlement']])
        <label class="flw-field"><span class="flw-field__label">From</span><input class="flw-input flw-input--sm" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label class="flw-field"><span class="flw-field__label">To</span><input class="flw-input flw-input--sm" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <button type="submit" class="flw-btn flw-btn--primary flw-btn--sm">Show</button>
    </form>

    @if ($panel['error'])
        <p class="flw-notice flw-notice--error">Couldn’t load settlements: {{ $panel['error'] }}</p>
    @elseif ($panel['items'] === [])
        <p class="flw-text">No settlements in this period.</p>
    @else
        <ul class="flw-rows">
            @foreach ($panel['items'] as $settlement)
                @php($open = ($selected['id'] ?? null) === ($settlement['id'] ?? false))
                <li>
                    <div class="flw-row">
                        <div class="flw-stack">
                            <span class="flw-amount">{{ $money($settlement['currency'] ?? null, $settlement['net_amount'] ?? null) }}</span>
                            <span class="flw-meta">
                                <span>{{ $settlement['transaction_count'] ?? 0 }} {{ ($settlement['transaction_count'] ?? 0) === 1 ? 'payment' : 'payments' }} on {{ $date($settlement['transaction_date'] ?? null, false) }}</span>
                                <span>Due {{ $date($settlement['due_date'] ?? null, false) }}</span>
                                @if (! empty($settlement['processed_date']))<span>Paid {{ $date($settlement['processed_date'], false) }}</span>@endif
                            </span>
                        </div>
                        <span class="flw-pill" data-tone="{{ $tone($settlement['status'] ?? null) }}">{{ ucfirst((string) ($settlement['status'] ?? '')) }}</span>
                    </div>
                    @if (! empty($settlement['flag_message']))
                        <p class="flw-notice flw-notice--warning">{{ $settlement['flag_message'] }}</p>
                    @endif
                    <a class="flw-link-btn" href="{{ $urlWith(['settlement' => $open ? null : $settlement['id']]) }}">{{ $open ? 'Hide details' : 'Details' }}</a>
                    @if ($open)
                        <dl class="flw-dl">
                            <div><dt>Collected</dt><dd>{{ $money($selected['currency'] ?? null, $selected['gross_amount'] ?? null) }}</dd></div>
                            <div><dt>Flutterwave fees</dt><dd>{{ $money($selected['currency'] ?? null, ($selected['app_fee'] ?? 0) + ($selected['merchant_fee'] ?? 0) + ($selected['stampduty_charge'] ?? 0)) }}</dd></div>
                            <div><dt>Refunds and chargebacks</dt><dd>{{ $money($selected['currency'] ?? null, ($selected['refund'] ?? 0) + ($selected['chargeback'] ?? 0)) }}</dd></div>
                            <div><dt>Paid to you</dt><dd>{{ $money($selected['currency'] ?? null, $selected['net_amount'] ?? null) }}</dd></div>
                        </dl>
                        @if ($selected['transactions'] !== [])
                            <div class="flw-table-wrap">
                                <table class="flw-table">
                                    <thead><tr><th>Reference</th><th>Customer</th><th>Charged</th><th>Fee</th><th>Settled</th></tr></thead>
                                    <tbody>
                                        @foreach ($selected['transactions'] as $transaction)
                                            <tr>
                                                <td>{{ $transaction['tx_ref'] ?? $transaction['id'] ?? '' }}</td>
                                                <td>{{ $transaction['customer_email'] ?? '' }}</td>
                                                <td>{{ number_format((float) ($transaction['charged_amount'] ?? 0), 2) }}</td>
                                                <td>{{ number_format((float) ($transaction['app_fee'] ?? 0), 2) }}</td>
                                                <td class="flw-strong">{{ number_format((float) ($transaction['settlement_amount'] ?? 0), 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @include('flutterwave::components.pager', ['page' => $panel['page'], 'urlWith' => $urlWith])
</div>
