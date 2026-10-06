@once
<script src="https://checkout.flutterwave.com/v3.js"></script>
@endonce
<button type="button" id="{{ $buttonId }}" {{ $attributes->merge(['class' => 'flutterwave-pay-button']) }}>
    @if (trim((string) $slot) !== ''){{ $slot }}@else{{ $label }}@endif
</button>
<script>
    document.getElementById(@json($buttonId)).addEventListener('click', function () {
        var config = {!! $checkoutConfig() !!};
        config.onclose = function (incomplete) {
            if (incomplete === true && config.redirect_url) {
                var separator = config.redirect_url.indexOf('?') === -1 ? '?' : '&';
                window.location.href = config.redirect_url + separator + 'status=cancelled&tx_ref=' + encodeURIComponent(config.tx_ref);
            }
        };
        FlutterwaveCheckout(config);
    });
</script>
