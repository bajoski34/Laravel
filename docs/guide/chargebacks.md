# Chargebacks

When a customer disputes a payment, you have a short window to accept it (and refund) or decline it with proof that they received what they paid for.

## Get told about them

Ask Flutterwave support to turn on chargeback webhooks for your account, then either listen for the event:

```php
use Flutterwave\Payments\Events\ChargebackUpdated;

Event::listen(function (ChargebackUpdated $event) {
    if ($event->needsResponse()) {
        // notify your team, open a ticket...
    }
});
```

or let the package email you:

```dotenv
FLW_NOTIFY_CHARGEBACKS=ops@your-app.com
```

## Respond

```php
use Flutterwave\Payments\Facades\Flutterwave;

Flutterwave::chargebacks()->all(['status' => 'initiated']);

// Accept: the customer is refunded
Flutterwave::chargebacks()->accept($id, 'Order was never shipped');

// Decline with evidence: one JPEG, PNG or PDF under 8MB
Flutterwave::chargebacks()->declineWithProof($id, 'Signed delivery note attached', storage_path('proof/1042.pdf'));
```

From the terminal:

```bash
php artisan flutterwave:chargebacks --status=initiated
php artisan flutterwave:decline-chargeback 1337 --proof=delivery-note.pdf --comment="Delivered 3 Oct"
php artisan flutterwave:accept-chargeback 1337 --comment="Refunding"
```

Or with the `Chargebacks` component on the [admin page](/guide/admin), which shows each dispute's deadline.
