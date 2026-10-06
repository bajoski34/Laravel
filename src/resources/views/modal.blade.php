<script src="https://checkout.flutterwave.com/v3.js"></script>
<script>
    (function () {
        var flw_detail = {!! $payment_details !!};
        flw_detail.onclose = function (incomplete) {
            if (incomplete === true && flw_detail.redirect_url) {
                var separator = flw_detail.redirect_url.indexOf('?') === -1 ? '?' : '&';
                window.location.href = flw_detail.redirect_url + separator + 'status=cancelled&tx_ref=' + encodeURIComponent(flw_detail.tx_ref);
            }
        };
        FlutterwaveCheckout(flw_detail);
    })();
</script>
