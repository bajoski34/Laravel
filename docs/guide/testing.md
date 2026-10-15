# Testing

`Flutterwave::fake()` answers every Flutterwave API call in your tests with a realistic response, without touching the network or your other `Http::fake()` stubs. It also sets placeholder keys, so your test environment doesn't need real ones.

## Checkout

```php
use Flutterwave\Payments\Facades\Flutterwave;

it('sends the customer to checkout', function () {
    $flutterwave = Flutterwave::fake();

    $this->actingAs($user)->post('/orders/42/pay')->assertRedirect();

    $flutterwave->assertCheckoutCreated(fn (array $payment) => $payment['amount'] === 5000);
});
```

## Verifying payments

Register the transactions your code will verify. Unregistered ids come back as "not found", so a test can't pass by accident.

```php
Flutterwave::fake()->transaction(4975363, ['amount' => 5000, 'tx_ref' => 'ORDER-42']);

expect(Flutterwave::isSuccessful(4975363, 5000))->toBeTrue();
```

## Webhooks

```php
use Flutterwave\Payments\Events\ChargeCompleted;
use Flutterwave\Payments\Testing\FlutterwaveFake;

it('marks the order paid', function () {
    Flutterwave::fake()->transaction(4975363, ['amount' => 5000, 'tx_ref' => 'ORDER-42']);

    $this->postJson('/flutterwave/webhook', [
        'event' => 'charge.completed',
        'data' => ['id' => 4975363, 'tx_ref' => 'ORDER-42', 'status' => 'successful'],
    ], FlutterwaveFake::webhookHeaders())->assertOk();

    expect(Order::firstWhere('reference', 'ORDER-42')->paid)->toBeTrue();
});
```

`webhookHeaders()` returns a header that passes the signature check. The fake also answers the re-verification call, so `ChargeCompleted` fires with the transaction you registered.

## Custom responses and failures

```php
Flutterwave::fake()
    ->respond('GET', 'balances', ['status' => 'success', 'data' => [['currency' => 'NGN', 'available_balance' => 0]]])
    ->failing('POST', 'transfers', 'Insufficient balance');
```

## Assertions

| Assertion | Passes when |
| --- | --- |
| `assertCheckoutCreated(?fn)` | A hosted checkout was started |
| `assertTransferSent(?fn)` | A transfer was created |
| `assertCardCharged(?fn)` | A saved card was charged |
| `assertRefunded($id, ?$amount)` | A transaction was refunded |
| `assertBillPaid(?fn)` | A bill was paid |
| `assertSent($method, $path, ?fn)` | Any matching request was sent |
| `assertNotSent($method, $path)` | No matching request was sent |
| `assertNothingSent()` | No requests at all |

`sent($method, $path)` returns the matching request payloads for your own checks.

## Testing in the sandbox

With test keys you can steer transfers to succeed or fail: see [test outcomes](/guide/transfers#test-outcomes-in-the-sandbox).
