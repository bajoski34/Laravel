<script src="https://checkout.flutterwave.com/v3.js"></script>
<script>
    function makePayment() {
        var flw_detail = JSON.parse('{!! html_entity_decode($payment_details) !!}');
        flw_detail.onclose = function(incomplete) {
            // close modal
            let callback_url = flw_detail.redirect_url;
            let tx_ref = flw_detail.tx_ref;
            if (incomplete === true) {
                // window.history.back();
                window.location.href = `${callback_url}?cancel=cancelled&tx_ref=${tx_ref}`;
            } else {
                window.location.href = `${callback_url}?status=succesful&txref=${tx_ref}`;
            }
        }
        FlutterwaveCheckout(flw_detail);
    }
    makePayment();
</script>

