@include('flutterwave::components.partials.styles')
<div {{ $attributes->merge(['class' => 'flw']) }}>
    @if (session('flutterwave.status'))
        <p class="flw-notice flw-notice--success" role="status">{{ session('flutterwave.status') }}</p>
    @endif
    @if ($error = $firstError(['flutterwave', 'proof', 'comment']))
        <p class="flw-notice flw-notice--error" role="alert">{{ $error }}</p>
    @endif

    @if ($panel['error'])
        <p class="flw-notice flw-notice--error">Couldn’t load chargebacks: {{ $panel['error'] }}</p>
    @elseif ($panel['items'] === [])
        <p class="flw-text">No chargebacks. When a customer disputes a payment with their bank, it shows up here.</p>
    @else
        <ul class="flw-rows">
            @foreach ($panel['items'] as $chargeback)
                @php([$label, $tone] = \Flutterwave\Payments\View\Components\Chargebacks::tone($chargeback['status']))
                <li>
                    <div class="flw-row">
                        <div class="flw-stack">
                            <span class="flw-amount">{{ number_format((float) $chargeback['amount'], 2) }}</span>
                            <span class="flw-meta"><span>{{ $chargeback['tx_ref'] }}</span><span>{{ $chargeback['flw_ref'] }}</span></span>
                        </div>
                        <span class="flw-pill" data-tone="{{ $tone }}">{{ $label }}</span>
                    </div>

                    @if ($chargeback['respond_within'] !== null)
                        <p class="{{ $chargeback['respond_within'] < 7200 ? 'flw-urgent' : 'flw-text' }}">
                            @if ($chargeback['respond_within'] > 0)
                                Respond within {{ \Flutterwave\Payments\View\Components\Chargebacks::deadline($chargeback['respond_within']) }}, or the customer is refunded automatically.
                            @else
                                The response deadline has passed.
                            @endif
                        </p>
                    @endif

                    @if ($chargeback['comment'])
                        <p class="flw-text">Customer’s reason: {{ $chargeback['comment'] }}</p>
                    @endif

                    @if ($chargeback['urls'])
                        <details class="flw-details">
                            <summary>Dispute with proof</summary>
                            <form method="POST" action="{{ $chargeback['urls']['decline'] }}" enctype="multipart/form-data" class="flw-panel">
                                @csrf
                                <label class="flw-field">
                                    <span class="flw-field__label">Evidence</span>
                                    <input class="flw-input" type="file" name="proof" required accept="{{ collect($panel['proof']['types'])->map(fn ($type) => '.'.$type)->implode(',') }}">
                                    <span class="flw-field__hint">One JPEG, PNG or PDF under 8MB. Merge receipts, delivery notes and messages into one file.</span>
                                </label>
                                <label class="flw-field">
                                    <span class="flw-field__label">What does your evidence show?</span>
                                    <textarea class="flw-input" name="comment" rows="2" required maxlength="1000"></textarea>
                                </label>
                                <button type="submit" class="flw-btn flw-btn--primary">Send dispute</button>
                            </form>
                        </details>
                        <details class="flw-details">
                            <summary>Accept and refund</summary>
                            <form method="POST" action="{{ $chargeback['urls']['accept'] }}" class="flw-panel">
                                @csrf
                                <label class="flw-field">
                                    <span class="flw-field__label">Why are you accepting it?</span>
                                    <textarea class="flw-input" name="comment" rows="2" required maxlength="1000"></textarea>
                                </label>
                                <label class="flw-checkbox"><input type="checkbox" required>@include('flutterwave::components.partials.check')<span>The customer gets their money back, and this can’t be undone.</span></label>
                                <button type="submit" class="flw-btn flw-btn--secondary">Accept and refund the customer</button>
                            </form>
                        </details>
                    @endif

                    @if ($chargeback['history'] !== [])
                        <details class="flw-details">
                            <summary>History</summary>
                            <ol class="flw-timeline">
                                @foreach ($chargeback['history'] as $entry)
                                    <li>{{ $entry['description'] }}{{ $entry['comment'] ? ': '.$entry['comment'] : '' }} <span class="flw-muted">{{ $entry['date'] }}</span></li>
                                @endforeach
                            </ol>
                        </details>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($panel['page'] && $panel['page']['total_pages'] > 1)
        <p class="flw-muted">Page {{ $panel['page']['current_page'] }} of {{ $panel['page']['total_pages'] }}, {{ $panel['page']['total'] }} in total.</p>
    @endif
</div>
