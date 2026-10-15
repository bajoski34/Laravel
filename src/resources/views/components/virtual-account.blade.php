@include('flutterwave::components.partials.styles')
<div id="{{ $componentId }}" {{ $attributes->merge(['class' => 'flw flw-card flw-card--narrow flw-account flutterwave-virtual-account']) }}
     @if ($expiresAt) data-expires-at="{{ $expiresAt }}" @endif>
    <div class="flw-stack">
        <h3 class="flw-title">Pay by bank transfer</h3>
        <p class="flw-text">Send the exact amount to this account from your banking app.</p>
    </div>
    <hr class="flw-rule">

    <div class="flw-account__details flw-stack" style="gap: 16px">
        <div class="flw-field">
            <span class="flw-field__label">Account number</span>
            <div class="flw-copyfield flw-account__number">
                <input class="flw-input" readonly value="{{ $details['account_number'] ?? '' }}" data-account-number aria-label="Account number">
                <button type="button" class="flw-copyfield__btn" data-copy>Copy</button>
            </div>
        </div>
        <dl class="flw-dl">
            <div><dt>Bank</dt><dd>{{ $details['bank_name'] ?? '' }}</dd></div>
            @if (! empty($details['amount']) && (float) $details['amount'] > 0)
                <div><dt>Amount to send</dt><dd>{{ $details['currency'] ?? $currency }} {{ number_format((float) $details['amount'], 2) }}</dd></div>
            @endif
        </dl>
        @if (! empty($details['note']))
            <p class="flw-muted">{{ $details['note'] }}</p>
        @endif
    </div>

    {{ $slot }}

    @if ($expiresAt)
        <p class="flw-countdown flutterwave-virtual-account__expiry"><span class="flw-ring" aria-hidden="true"></span>Expires in <strong data-countdown></strong></p>
    @elseif (! empty($details['expiry_date']) && $details['expiry_date'] !== 'N/A')
        <p class="flw-muted">Expires {{ $details['expiry_date'] }}</p>
    @endif
    <p class="flw-notice flw-notice--error" data-expired hidden>{{ $expiredMessage }}</p>
</div>
<script>
    (function () {
        var root = document.getElementById(@json($componentId));
        var copy = root.querySelector('[data-copy]');

        function done(label) {
            copy.textContent = label;
            setTimeout(function () { copy.textContent = 'Copy'; }, 2000);
        }

        // Browsers refuse the clipboard on plain http:// and in some embedded views: select the number instead.
        function selectNumber() {
            root.querySelector('[data-account-number]').select();
            done('Selected');
        }

        copy.addEventListener('click', function () {
            try {
                navigator.clipboard.writeText(root.querySelector('[data-account-number]').value).then(function () { done('Copied'); }, selectNumber);
            } catch (e) {
                selectNumber();
            }
        });

        if (!root.dataset.expiresAt) {
            return;
        }

        // Count down from the seconds left at render time, so a wrong clock on the customer's device doesn't matter.
        var deadline = Date.now() + (parseInt(root.dataset.expiresAt, 10) - {{ time() }}) * 1000;
        var countdown = root.querySelector('[data-countdown]');

        (function tick() {
            var left = Math.max(0, Math.round((deadline - Date.now()) / 1000));

            if (left === 0) {
                root.classList.add('is-expired');
                root.querySelector('.flutterwave-virtual-account__expiry').hidden = true;
                root.querySelector('[data-expired]').hidden = false;
                copy.disabled = true;

                return;
            }

            var hours = Math.floor(left / 3600), minutes = Math.floor(left % 3600 / 60), seconds = left % 60;
            countdown.textContent = (hours ? hours + ':' + String(minutes).padStart(2, '0') : minutes) + ':' + String(seconds).padStart(2, '0');
            setTimeout(tick, 1000);
        })();
    })();
</script>
