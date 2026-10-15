# Verifying payments

Before you ship an order, ask Flutterwave whether the payment really went through, for the amount you expected. Never trust the status in a redirect or a frontend callback.

## The short way

```php
use Flutterwave\Payments\Facades\Flutterwave;

if (Flutterwave::isSuccessful($transactionId, $order->total, $order->currency, $order->reference)) {
    $order->markPaid();
}
```

`isSuccessful()` returns `true` only when Flutterwave reports the transaction as successful **and**, when you pass them:

- the amount paid is at least `$expectedAmount`
- the currency matches `$expectedCurrency`
- the `tx_ref` matches `$expectedReference`

## The full response

```php
$response = Flutterwave::verifyTransaction($transactionId);   // by Flutterwave's id
$response = Flutterwave::verifyTransactionReference($txRef);  // by your tx_ref

$response['status'];          // 'success' if the API call worked
$response['data']['status'];  // 'successful', 'pending' or 'failed'
$response['data']['amount'];
```

Compare against the constants in `Flutterwave\Payments\Data\Status` (`Status::SUCCESSFUL`, `Status::PENDING`, `Status::FAILED`).

## In the redirect callback

Flutterwave appends `status`, `tx_ref` and `transaction_id` to your redirect URL. The published callback route re-verifies before redirecting:

```php
Route::get('/flutterwave/payment/callback', function (ConfirmRequest $request) {
    $validated = $request->validated();

    if ($validated['status'] === 'cancelled') {
        return redirect()->route('flutterwave.cancelled');
    }

    $response = empty($validated['transaction_id'])
        ? Flutterwave::verifyTransactionReference($validated['tx_ref'])
        : Flutterwave::verifyTransaction($validated['transaction_id']);

    return match ($response['data']['status'] ?? null) {
        Status::SUCCESSFUL => redirect()->route('flutterwave.successful'),
        Status::PENDING => redirect()->route('flutterwave.pending'),
        default => redirect()->route('flutterwave.failed'),
    };
});
```

## Prefer the webhook

The customer can close the tab before the redirect, and bank transfers and USSD payments can complete minutes later. The [`ChargeCompleted`](/guide/webhooks) event fires for every payment, already re-verified against the API, so it's the most reliable place to fulfil orders.

## From the terminal

```bash
php artisan flutterwave:verify 4975363
php artisan flutterwave:verify LARAVEL-aB3xQ1729000000 --ref
```
