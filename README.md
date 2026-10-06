<p align="center">
    <img title="Flutterwave" height="200" src="https://flutterwave.com/images/logo/full.svg" width="50%"/>
</p>

# Flutterwave for Laravel

[![Tests](https://github.com/bajoski34/Laravel/actions/workflows/php.yml/badge.svg)](https://github.com/bajoski34/Laravel/actions/workflows/php.yml)
![Packagist Downloads](https://img.shields.io/packagist/dt/abraham-flutterwave/laravel-payment)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/abraham-flutterwave/laravel-payment)
![GitHub stars](https://img.shields.io/github/stars/bajoski34/Laravel)
![Packagist License](https://img.shields.io/packagist/l/abraham-flutterwave/laravel-payment)

Accept payments across Africa and beyond, send payouts, bill subscriptions and react to webhooks, all from Laravel with very little code.

```php
return Flutterwave::redirect(['amount' => 5000, 'email' => 'jane@example.com']);
```

That one line creates a hosted checkout supporting cards, bank transfer, USSD, mobile money and more, then sends your customer to it.

**If this package saves you time, please ⭐ [star it on GitHub](https://github.com/bajoski34/Laravel). It helps other developers find it.**

## Features

| | |
|---|---|
| **Checkout** | Hosted payment links, inline modal, drop-in `<x-flutterwave-button>` |
| **Verification** | `Flutterwave::isSuccessful($id, $amount, $currency)` in one call |
| **Webhooks** | Route, signature check and Laravel events wired up for you |
| **Transfers** | Single and bulk payouts to banks and mobile money, fees, FX rates |
| **Stablecoins** | Send USDC, USDT and RLUSD on Solana, Ethereum, Base and Polygon; fund from fiat |
| **Banks** | Bank lists, branches, account-name resolution |
| **Subscriptions** | Payment plans and subscription management |
| **Virtual accounts** | Static and dynamic account numbers for bank transfer collections |
| **Split payments** | Subaccounts for marketplaces |
| **Refunds** | Full and partial refunds, refund lookups |
| **Balances & settlements** | Wallet balances and settlement history |
| **Artisan** | `flutterwave:install`, `flutterwave:verify`, `flutterwave:banks` |

Supports **PHP 8.1+** and **Laravel 9, 10, 11, 12 and 13**.

## Installation

```shell
composer require abraham-flutterwave/laravel-payment
php artisan flutterwave:install
```

The install command publishes `config/flutterwave.php`, adds the keys below to your `.env`, and prints your webhook URL:

```env
FLW_PUBLIC_KEY=FLWPUBK_TEST-xxxxxxxx
FLW_SECRET_KEY=FLWSECK_TEST-xxxxxxxx
FLW_SECRET_HASH=any-random-string     # paste the same value into Settings > Webhooks
FLW_ENCRYPTION_KEY=xxxxxxxx
```

Get your keys from the [Flutterwave dashboard](https://app.flutterwave.com/dashboard/settings/apis). Test keys work against the sandbox automatically.

## Accepting payments

### Hosted checkout (recommended)

```php
use Flutterwave\Payments\Facades\Flutterwave;

Route::post('/pay', function () {
    return Flutterwave::redirect([
        'amount' => 5000,
        'currency' => 'NGN',            // optional, defaults to FLW_DEFAULT_CURRENCY
        'email' => auth()->user()->email,
        'meta' => ['order_id' => 42],   // optional, returned in webhooks
    ]);
});
```

Need just the URL (for an API or mobile app)? Use `Flutterwave::checkout([...])`, which returns the link. `tx_ref` is generated for you; pass your own to tie the payment to an order.

### Inline modal button

```blade
<x-flutterwave-button amount="5000" email="{{ auth()->user()->email }}" currency="NGN" class="btn btn-primary">
    Pay ₦5,000
</x-flutterwave-button>
```

Supported attributes: `amount`, `email`, `currency`, `name`, `phone`, `tx-ref`, `redirect-url`, `payment-options`, `payment-plan`, `:meta`, `:customizations`, `:subaccounts`.

### Confirming a payment

Flutterwave redirects the customer to `FLW_REDIRECT_URL` with `status`, `tx_ref` and `transaction_id`. Never trust those query parameters. Verify on the server instead:

```php
Route::get('/flutterwave/payment/callback', function (Request $request) {
    $order = Order::where('reference', $request->tx_ref)->firstOrFail();

    if ($request->transaction_id && Flutterwave::isSuccessful($request->transaction_id, $order->amount, $order->currency)) {
        $order->markAsPaid();

        return redirect()->route('orders.show', $order);
    }

    return redirect()->route('checkout')->with('error', 'Payment was not completed.');
});
```

`isSuccessful()` returns `true` only when Flutterwave reports the transaction as successful **and** the amount and currency match what you expected.

For the raw response, use `Flutterwave::verifyTransaction($id)` or `Flutterwave::verifyTransactionReference($txRef)`.

## Webhooks

Webhooks are the reliable way to learn about payments, since customers sometimes close the browser before the redirect. The package registers `POST /flutterwave/webhook` for you. It:

1. rejects any request whose signature doesn't match `FLW_SECRET_HASH`,
2. re-fetches `charge.completed` transactions from the Flutterwave API, so a forged payload can't mark an order as paid,
3. dispatches Laravel events you can listen to.

Set the URL printed by `php artisan flutterwave:install` as your webhook URL on the dashboard, then listen for events:

```php
use Flutterwave\Payments\Events\ChargeCompleted;
use Illuminate\Support\Facades\Event;

// e.g. in AppServiceProvider::boot()
Event::listen(function (ChargeCompleted $event) {
    if (! $event->isSuccessful()) {
        return;
    }

    $order = Order::where('reference', $event->reference())->first();

    if ($order && $event->amount() >= $order->amount && $event->currency() === $order->currency) {
        $order->markAsPaid();
    }
});
```

| Event | When |
|---|---|
| `WebhookReceived` | Every verified webhook (`$event->event`, `$event->data`, `$event->payload`) |
| `ChargeCompleted` | A payment completed. `$event->transaction` is the API-verified transaction |
| `TransferCompleted` | A payout succeeded or failed |
| `SubscriptionCancelled` | A customer's subscription was cancelled |

Change the path, add middleware or turn the route off under `webhook` in `config/flutterwave.php`.

## Transfers (payouts)

```php
// Look up the account name before sending money
$account = Flutterwave::banks()->resolveAccount('0690000032', '044');

$transfer = Flutterwave::transfers()->create([
    'account_bank' => '044',
    'account_number' => '0690000032',
    'amount' => 5500,
    'currency' => 'NGN',
    'narration' => 'Vendor payout',
]);

Flutterwave::transfers()->bulk([...], 'March payroll');
Flutterwave::transfers()->fee(5500, 'NGN');
Flutterwave::transfers()->rates(1000, 'NGN', 'USD');
Flutterwave::transfers()->find($id);
Flutterwave::transfers()->retry($id);
```

Don't know a bank code? Run `php artisan flutterwave:banks NG --search=access`.

## Stablecoins

Send USDC, USDT and RLUSD to crypto wallets, or convert fiat into your stablecoin balance. This requires a live, production-approved account with [whitelisted IPs](https://flutterwave.com/ng/support/integrations/how-to-whitelist-ip-addresses-on-your-flutterwave-dashboard).

```php
use Flutterwave\Payments\Data\Stablecoin;

// Check the fee first. For stablecoin-to-stablecoin sends it is deducted from the amount.
Flutterwave::stablecoins()->fee(50, Stablecoin::USDT);

// Pay from your USDC balance
Flutterwave::stablecoins()->send(Stablecoin::POLYGON, '0xd0c7...', 50, Stablecoin::USDC);

// Or convert from NGN and pay in one step (the fee is charged to your NGN balance)
Flutterwave::stablecoins()->send(Stablecoin::SOLANA, '7EcDh...', 50, Stablecoin::USDT, 'NGN');

// Top up your USDC wallet from NGN, USD, GBP, EUR or GHS (needs FLW_MERCHANT_ID)
Flutterwave::stablecoins()->fund(100, Stablecoin::USDC, 'USD');

Flutterwave::stablecoins()->balances();   // ['USDC' => [...], 'USDT' => [...]]
Flutterwave::stablecoins()->find($transferId);
```

| Network | Coins |
|---|---|
| `SOLANA` | USDT, USDC |
| `ETHEREUM` | USDT, USDC, RLUSD |
| `BASE` | USDC |
| `POLYGON` | USDT, USDC |

Crypto transfers can't be reversed, so before calling Flutterwave, `send()` rejects coins a network doesn't support (e.g. USDT on Base), unsupported networks such as Tron, and addresses in the wrong format for the chain. You can't send USDC to a USDT address. Completed transfers fire the `TransferCompleted` event.

## Subscriptions

```php
$plan = Flutterwave::plans()->create('Pro', 5000, 'monthly');

// Subscribe a customer by checking out against the plan
return Flutterwave::redirect([
    'amount' => 5000,
    'email' => $user->email,
    'payment_plan' => $plan['data']['id'],
]);

Flutterwave::subscriptions()->all(['email' => $user->email]);
Flutterwave::subscriptions()->cancel($subscriptionId);
```

## Virtual accounts

```php
$account = Flutterwave::virtualAccounts()->create([
    'email' => $user->email,
    'is_permanent' => true,
    'bvn' => '12345678901',
    'narration' => $user->name,
]);

$account['data']['account_number']; // your customer pays into this account
```

## Split payments

```php
$vendor = Flutterwave::subaccounts()->create([
    'account_bank' => '044',
    'account_number' => '0690000037',
    'business_name' => 'Jane Store',
    'business_mobile' => '09087930450',
    'country' => 'NG',
    'split_type' => 'percentage',
    'split_value' => 0.1,
]);

Flutterwave::redirect([
    'amount' => 10000,
    'email' => 'buyer@example.com',
    'subaccounts' => [['id' => $vendor['data']['subaccount_id']]],
]);
```

## More

```php
Flutterwave::refund($transactionId);              // full refund
Flutterwave::refund($transactionId, 1000);        // partial refund
Flutterwave::refunds()->all();
Flutterwave::balances()->currency('NGN');
Flutterwave::settlements()->all(['page' => 1]);
Flutterwave::beneficiaries()->create('0690000032', '044', 'Jane Doe');
Flutterwave::transactions()->all(['status' => 'successful']);
Flutterwave::transactions()->timeline($transactionId);
```

Every method returns Flutterwave's JSON response as an array (`status`, `message`, `data` and, for lists, `meta`).

```shell
php artisan flutterwave:verify 4975363          # by transaction id
php artisan flutterwave:verify ORDER-42 --ref   # by tx_ref
```

## Error handling

API errors throw `Flutterwave\Payments\Exception\FlutterwaveException`:

```php
use Flutterwave\Payments\Exception\FlutterwaveException;

try {
    Flutterwave::transfers()->create($payload);
} catch (FlutterwaveException $e) {
    $e->getMessage();     // "Insufficient wallet balance"
    $e->getStatusCode();  // 400
    $e->getResponse();    // full Flutterwave response body
}
```

Network failures throw `NetworkConnection`, a subclass of `FlutterwaveException`. Read-only (GET) requests are retried automatically. Requests that move money are **never** retried, so a timeout can't cause a double payout. Tune this with `FLW_TIMEOUT` and `FLW_RETRIES`.

> The `transactions()` service keeps its 2.1 behaviour of returning the error body instead of throwing, so existing code keeps working.

Everything the package does is logged to `storage/logs/flutterwave.log`.

## Testing your app

The package uses Laravel's HTTP client, so you can fake Flutterwave in your tests:

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.test']]),
]);

$this->post('/pay')->assertRedirect('https://checkout.test');
```

To test your webhook listeners, post to `/flutterwave/webhook` with a `verif-hash` header equal to your `FLW_SECRET_HASH`.

## Customising

- **Branding and defaults:** set the business name, logo, currency, country and payment methods in `config/flutterwave.php`.
- **Example routes:** run `php artisan vendor:publish --tag=flutterwave-routes` for ready-made checkout and callback routes in `routes/vendor/flutterwave/web.php`.
- **Views:** run `php artisan vendor:publish --tag=flutterwave-views`.
- **Swap a service:** map a key in `services`, e.g. `'transfers' => App\Payments\Transfers::class` (extend the original class), or set a key to `null` to disable that service.

## Upgrading from 2.1

Existing code keeps working: `render()`, `use()`, `verifyTransaction()`, `verifyTransactionReference()` and `generateTransactionReference()` are unchanged. A few things to note:

- PHP 8.1+ and Laravel 9+ are now required.
- If you published the example routes before, re-publish them with `--force` to pick up the fixed callback. They now run in the `web` middleware group, so add `@csrf` to checkout forms.
- You can delete your hand-written webhook route and use the built-in one with events.

See [CHANGELOG.md](CHANGELOG.md) for details.

## Contributing

Contributions are welcome! Read the [contribution guidelines](CONTRIBUTING.md), then:

```shell
composer install
composer test
composer format
```

Ideas for what's next: bill payments, card tokenisation and charges, payment links, and more Blade components. [Open an issue](https://github.com/bajoski34/Laravel/issues) to discuss.

## Support

- [Flutterwave API documentation](https://developer.flutterwave.com)
- [Flutterwave error reference](https://developer.flutterwave.com/docs/integration-guides/errors)
- Email the developer experience team at [developers@flutterwavego.com](mailto:developers@flutterwavego.com)
- Follow [@FlutterwaveEng](https://twitter.com/FlutterwaveEng)

## License

MIT. See [LICENSE](LICENSE). Copyright (c) Flutterwave Inc.
