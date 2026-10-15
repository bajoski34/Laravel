@once
<style>
    :where(.flutterwave-virtual-account) { display: grid; gap: .75rem; max-width: 24rem; padding: 1.25rem; border: 1px solid #e5e7eb; border-radius: .75rem; font-family: inherit; }
    :where(.flutterwave-virtual-account dl) { display: grid; gap: .5rem; margin: 0; }
    :where(.flutterwave-virtual-account dt) { font-size: .8rem; color: #6b7280; }
    :where(.flutterwave-virtual-account dd) { margin: 0; font-weight: 600; }
    :where(.flutterwave-virtual-account__number) { display: flex; align-items: center; gap: .5rem; font-size: 1.5rem; letter-spacing: .05em; font-variant-numeric: tabular-nums; }
    :where(.flutterwave-virtual-account__copy) { font-size: .8rem; padding: .25rem .6rem; border: 1px solid #d1d5db; border-radius: .375rem; background: transparent; cursor: pointer; }
    :where(.flutterwave-virtual-account__expiry) { font-size: .875rem; color: #6b7280; }
    :where(.flutterwave-virtual-account.is-expired .flutterwave-virtual-account__details) { opacity: .4; }
    :where(.flutterwave-virtual-account__expired) { color: #b91c1c; font-size: .875rem; }
</style>
@endonce
<div id="{{ $componentId }}" {{ $attributes->merge(['class' => 'flutterwave-virtual-account']) }}
     @if ($expiresAt) data-expires-at="{{ $expiresAt }}" @endif>
    <dl class="flutterwave-virtual-account__details">
        <div>
            <dt>Bank</dt>
            <dd>{{ $details['bank_name'] ?? '' }}</dd>
        </div>
        <div>
            <dt>Account number</dt>
            <dd class="flutterwave-virtual-account__number">
                <span data-account-number>{{ $details['account_number'] ?? '' }}</span>
                <button type="button" class="flutterwave-virtual-account__copy" data-copy>Copy</button>
            </dd>
        </div>
        @if (! empty($details['amount']) && (float) $details['amount'] > 0)
            <div>
                <dt>Amount to send</dt>
                <dd>{{ $details['currency'] ?? $currency }} {{ number_format((float) $details['amount'], 2) }}</dd>
            </div>
        @endif
    </dl>

    @if (! empty($details['note']))
        <p class="flutterwave-virtual-account__note">{{ $details['note'] }}</p>
    @endif

    {{ $slot }}

    @if ($expiresAt)
        <p class="flutterwave-virtual-account__expiry">Expires in <span data-countdown></span></p>
    @elseif (! empty($details['expiry_date']) && $details['expiry_date'] !== 'N/A')
        <p class="flutterwave-virtual-account__expiry">Expires {{ $details['expiry_date'] }}</p>
    @endif
    <p class="flutterwave-virtual-account__expired" data-expired hidden>{{ $expiredMessage }}</p>
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
            var range = document.createRange();
            range.selectNodeContents(root.querySelector('[data-account-number]'));
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);
            done('Selected');
        }

        copy.addEventListener('click', function () {
            try {
                navigator.clipboard.writeText(root.querySelector('[data-account-number]').textContent.trim()).then(function () { done('Copied'); }, selectNumber);
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
