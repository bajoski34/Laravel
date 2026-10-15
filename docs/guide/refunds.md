# Refunds

```php
use Flutterwave\Payments\Facades\Flutterwave;

Flutterwave::refund($transactionId);          // in full
Flutterwave::refund($transactionId, 1500);    // partially

Flutterwave::refund($transactionId, 1500, [
    'comments' => 'Item out of stock',
    'callbackurl' => route('refunds.callback'),  // Flutterwave posts the result here
]);
```

Look refunds up later:

```php
Flutterwave::refunds()->all(['from' => '2026-10-01', 'to' => '2026-10-15']);
Flutterwave::refunds()->find($refundId);
```

Refunds can also be issued from the [admin page](/guide/admin) and the `Refunds` component.

## Fees

Know what a payment will cost before you take it:

```php
Flutterwave::transactions()->fee(5000, 'NGN');
Flutterwave::transactions()->fee(5000, 'NGN', 'card', cardFirst6: '539983');
```
