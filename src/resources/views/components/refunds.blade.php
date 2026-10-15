@include('flutterwave::components.partials.styles')
@php($filters = $panel['filters'])
<div {{ $attributes->merge(['class' => 'flw']) }}>
    <form method="GET" class="flw-filters">
        @include('flutterwave::components.keep-query', ['except' => ['from', 'to', 'status']])
        <label class="flw-field"><span class="flw-field__label">From</span><input class="flw-input flw-input--sm" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label class="flw-field"><span class="flw-field__label">To</span><input class="flw-input flw-input--sm" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <label class="flw-field">
            <span class="flw-field__label">Status</span>
            <span class="flw-select">
                <select class="flw-input flw-input--sm" name="status">
                    <option value="">Any</option>
                    @foreach (['completed' => 'Completed', 'successful' => 'Successful', 'failed' => 'Failed'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @include('flutterwave::components.partials.chevron')
            </span>
        </label>
        <button type="submit" class="flw-btn flw-btn--primary flw-btn--sm">Show</button>
    </form>

    @if ($panel['error'])
        <p class="flw-notice flw-notice--error">Couldn’t load refunds: {{ $panel['error'] }}</p>
    @elseif ($panel['items'] === [])
        <p class="flw-text">No refunds match these filters. Refund a payment from its transaction.</p>
    @else
        <div class="flw-table-wrap">
            <table class="flw-table">
                <thead><tr><th>Refunded</th><th>Status</th><th>Transaction</th><th>Reference</th><th>Reason</th><th>Date</th></tr></thead>
                <tbody>
                    @foreach ($panel['items'] as $refund)
                        <tr>
                            <td class="flw-strong">{{ number_format((float) $refund['amount_refunded'], 2) }}</td>
                            <td><span class="flw-pill" data-tone="{{ $tone($refund['status']) }}">{{ ucfirst(explode('-', (string) $refund['status'])[0]) }}</span></td>
                            <td>{{ $refund['transaction_id'] }}</td>
                            <td>{{ $refund['flw_ref'] }}</td>
                            <td>{{ $refund['comment'] }}</td>
                            <td>{{ $date($refund['created_at']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @include('flutterwave::components.pager', ['page' => $panel['page'], 'urlWith' => $urlWith])
</div>
