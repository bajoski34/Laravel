<script src="https://checkout.flutterwave.com/v3.js"></script>
<script>
    function makePayment() {
        FlutterwaveCheckout(JSON.parse('{!! html_entity_decode($payment_details) !!}'));
    }
    makePayment();
</script>

