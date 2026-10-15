# Saved cards

After a customer pays by card once, Flutterwave gives you a token you can charge again without the customer re-entering their card. The `Billable` trait stores it on your model and charges it.

## Set up

```bash
php artisan flutterwave:install --billable
php artisan migrate
```

The migration adds `flutterwave_card_token`, `_email`, `_last4`, `_type`, `_expiry` and `_saved_at` columns to `users`. Edit the published migration first if your billable model uses another table.

```php
use Flutterwave\Payments\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

The token is stored encrypted, and the token and email are hidden from arrays and JSON, Inertia props included.

## Save the card after a payment

```php
use Flutterwave\Payments\Events\ChargeCompleted;

Event::listen(function (ChargeCompleted $event) {
    if ($event->isSuccessful() && ($event->transaction['payment_type'] ?? null) === 'card') {
        User::where('email', $event->customerEmail())->first()?->saveFlutterwaveCard($event->transaction);
    }
});
```

Only cards from successful payments can be saved; anything else throws `InvalidArgument`.

## Charge it

```php
$response = $user->chargeSavedCard(5000, [
    'currency' => 'NGN',
    'tx_ref' => $invoice->reference,
]);

$response['data']['status']; // can be 'pending' while the customer approves 3-D Secure
```

## Hold now, capture later

```php
$hold = $user->preauthorizeSavedCard(5000);

// Later, settle it with the preauth service
Flutterwave::preauth()->capture($hold['data']['flw_ref'], 4500); // capture part or all
Flutterwave::preauth()->void($hold['data']['flw_ref']);          // or release it
```

## Show and manage the card

```php
$user->hasFlutterwaveCard();          // bool
$card = $user->flutterwaveCard();     // ?SavedCard

$card->label();                       // "MASTERCARD ending 2950"
$card->expiry;                        // "09/27"
$card->expiresWithin(30);             // token or card runs out in the next 30 days

$user->forgetFlutterwaveCard();
```

Flutterwave tokens last a year from when the card was saved. When `expiresWithin()` is true, ask the customer to pay by card once more to renew it.

## Charging many cards

For renewals in bulk, use the cards service directly:

```php
Flutterwave::cards()->chargeMany([
    ['token' => $token, 'email' => $email, 'amount' => 5000, 'currency' => 'NGN', 'tx_ref' => $ref],
    // ...
], title: 'October renewals');
```

Check progress with `bulkStatus($id)` and `bulkTransactions($id)`.
