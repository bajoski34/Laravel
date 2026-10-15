@include('flutterwave::components.admin-styles')
<div {{ $attributes->merge(['class' => 'flw-admin']) }}>
    @if (session('flutterwave.status'))
        <p class="flw-ok" role="status">{{ session('flutterwave.status') }}</p>
    @endif
    @if ($error = $firstError(['flutterwave', 'proof', 'comment']))
        <p class="flw-error" role="alert">{{ $error }}</p>
    @endif

    @if ($panel['error'])
        <p class="flw-error">Couldn’t load chargebacks: {{ $panel['error'] }}</p>
    @elseif ($panel['items'] === [])
        <p class="flw-muted">No chargebacks. When a customer disputes a payment with their bank, it shows up here.</p>
    @endif

    @foreach ($panel['items'] as $chargeback)
        @php([$label, $tone] = \Flutterwave\Payments\View\Components\Chargebacks::tone($chargeback['status']))
        <article class="flw-card">
            <div class="flw-row">
                <div>
                    <div class="flw-amount">{{ number_format((float) $chargeback['amount'], 2) }}</div>
                    <div class="flw-muted">{{ $chargeback['tx_ref'] }} · {{ $chargeback['flw_ref'] }}</div>
                </div>
                <span class="flw-pill" data-tone="{{ $tone }}">{{ $label }}</span>
            </div>

            @if ($chargeback['respond_within'] !== null)
                <p class="{{ $chargeback['respond_within'] < 7200 ? 'flw-urgent' : '' }}">
                    @if ($chargeback['respond_within'] > 0)
                        Respond within {{ \Flutterwave\Payments\View\Components\Chargebacks::deadline($chargeback['respond_within']) }}, or the customer is refunded automatically.
                    @else
                        The response deadline has passed.
                    @endif
                </p>
            @endif

            @if ($chargeback['comment'])
                <p>Customer’s reason: {{ $chargeback['comment'] }}</p>
            @endif

            @if ($chargeback['urls'])
                <details>
                    <summary>Dispute with proof</summary>
                    <form method="POST" action="{{ $chargeback['urls']['decline'] }}" enctype="multipart/form-data">
                        @csrf
                        <label>Evidence
                            <input type="file" name="proof" required accept="{{ collect($panel['proof']['types'])->map(fn ($type) => '.'.$type)->implode(',') }}">
                            <span class="flw-muted">One JPEG, PNG or PDF under 8MB. Merge receipts, delivery notes and messages into one file.</span>
                        </label>
                        <label>What does your evidence show?
                            <textarea name="comment" rows="2" required maxlength="1000"></textarea>
                        </label>
                        <button type="submit">Send dispute</button>
                    </form>
                </details>
                <details>
                    <summary>Accept and refund</summary>
                    <form method="POST" action="{{ $chargeback['urls']['accept'] }}">
                        @csrf
                        <label>Why are you accepting it?
                            <textarea name="comment" rows="2" required maxlength="1000"></textarea>
                        </label>
                        <label class="flw-check"><input type="checkbox" required> The customer gets their money back, and this can’t be undone.</label>
                        <button type="submit">Accept and refund the customer</button>
                    </form>
                </details>
            @endif

            @if ($chargeback['history'] !== [])
                <details>
                    <summary>History</summary>
                    <ol>
                        @foreach ($chargeback['history'] as $entry)
                            <li>{{ $entry['description'] }}{{ $entry['comment'] ? ': '.$entry['comment'] : '' }} <span class="flw-muted">{{ $entry['date'] }}</span></li>
                        @endforeach
                    </ol>
                </details>
            @endif
        </article>
    @endforeach

    @if ($panel['page'] && $panel['page']['total_pages'] > 1)
        <p class="flw-muted">Page {{ $panel['page']['current_page'] }} of {{ $panel['page']['total_pages'] }}, {{ $panel['page']['total'] }} in total.</p>
    @endif
</div>
