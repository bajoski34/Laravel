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
| **Inertia** | Inertia-aware redirects, plus publishable React, Vue and Svelte components for checkout and bank transfer payments |
| **Verification** | `Flutterwave::isSuccessful($id, $amount, $currency)` in one call |
| **Webhooks** | Route, signature check and Laravel events wired up for you |
| **Transfers** | Single and bulk payouts to banks and mobile money, fees, FX rates |
| **Stablecoins** | Send USDC, USDT and RLUSD on Solana, Ethereum, Base and Polygon; fund from fiat |
| **Banks** | Bank lists, branches, account-name resolution |
| **Subscriptions** | Payment plans and subscription management |
| **Virtual accounts** | Static accounts, plus a drop-in `<x-flutterwave-virtual-account>` component for one-off bank transfer payments |
| **Split payments** | Subaccounts for marketplaces |
| **Refunds** | Full and partial refunds with a reason, refund history |
| **Balances & settlements** | Wallet balances, and settlements with their fees and the payments in each |
| **Beneficiaries** | Saved transfer recipients, and a bank dropdown that submits the bank code |
| **Chargebacks** | List disputes, accept them, or decline with uploaded proof |
| **FX trades** | Request a quote and convert NGN, GHS and USD at the quoted rate |
| **Saved cards** | Charge a customer's card again with a `Billable` trait; pre-authorize, capture and void holds |
| **Bill payments** | Airtime, data, electricity and cable TV, with customer number validation |
| **Wallets** | Payout subaccounts: a balance and bank account number for each customer |
| **BVN verification** | Consent-based BVN checks for Nigerian customers |
| **Testing** | `Flutterwave::fake()` with realistic responses and assertions |
| **Artisan** | `flutterwave:doctor` to check your setup, plus commands to verify payments, check balances, look up banks, accounts, card BINs and bills, and replay webhooks locally |

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

### The Flutterwave look

Every component, in Blade, React, Vue and Svelte, has the same look as Flutterwave's WooCommerce onboarding and settings screens:
- **Fonts:** Millik for headings and Moderat for text.
- **Buttons:** orange primary buttons with dark text.
- **Fields and tabs:** roomy fields with an orange focus ring, and tabs underlined in orange.

They share one stylesheet, `flutterwave.css`, and the fonts ship with the package, so there are no third-party font requests and no Tailwind setup is needed:

- **Blade:** components link the stylesheet themselves, served by the package from `/flutterwave/assets` (change the path with the `assets_path` config key). It's linked once per page and cached for a year per package version.
- **React, Vue and Svelte:** the published `index.ts` imports `flutterwave.css`, so Vite bundles it and the fonts with your app.

Restyle everything at once by overriding the CSS tokens on `.flw`. For example, `.flw { --flw-orange: #f5a623; --flw-radius: 4px; }`. The components are light by default, like the plugin. They switch to dark colours when your app turns dark mode on with a `dark` class or `data-theme="dark"` on a parent element. A `className` (or `class`) you pass is added to a component's own classes.

### Publishing files

`flutterwave:install` publishes only the config. Everything else is opt-in: publish just the parts you need, by tag or with an install option.

| What | Tag | Install option | Goes to |
|---|---|---|---|
| Config | `flutterwave-config` | *(always)* | `config/flutterwave.php` |
| React, Vue or Svelte components | `flutterwave-react`, `flutterwave-vue`, `flutterwave-svelte` | `--react`, `--vue`, `--svelte` | `resources/js/components/flutterwave/` |
| Saved card columns | `flutterwave-migrations` | `--billable` | `database/migrations/` |
| Blade views | `flutterwave-views` | | `resources/views/vendor/flutterwave/` |
| Example checkout routes | `flutterwave-routes` | | `routes/vendor/flutterwave/web.php` |

```shell
php artisan vendor:publish --tag=flutterwave-views
```

Before you publish:

- **Components:** publish one framework only. They all go to the same folder, and `flutterwave:install` refuses to publish two.
- **Migration:** it adds columns to `users`. Edit the `$table` property first if your billable model uses another table.
- **Views:** published copies replace the package's, so later fixes to them won't reach you. Publish them only to restyle them, and keep only the files you changed.
- **Example routes:** once published, they're loaded automatically and add public routes to your app, including `POST /flutterwave/payment/checkout`. Edit or delete the file to suit your app.
- **`--force` overwrites your edits.** The install command passes `--force` only when you give it `--force`.

> **Don't publish everything with `--provider`.** `php artisan vendor:publish --provider="Flutterwave\Payments\Providers\FlutterwaveServiceProvider"` publishes every tag at once. That puts all three frontends' components in one folder, adds the saved-card migration and the example routes, and copies every view. Use the tags above instead.

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

### Inertia (React, Vue, Svelte)

`Flutterwave::redirect()` works as-is from an Inertia form post. It sees the `X-Inertia` header and returns the response that makes Inertia do a full page visit to the hosted checkout, so the browser doesn't block a cross-domain XHR redirect.

For the inline modal, build the config on the server and pass it as a prop. That way the amount and `tx_ref` can't be changed in the browser:

```php
return Inertia::render('Checkout', [
    'checkout' => Flutterwave::inlineConfig(['amount' => $order->total, 'email' => $user->email, 'tx_ref' => 'ORDER-'.$order->id]),
]);
```

#### Components for React, Vue and Svelte

Publish the components for your frontend:

```shell
php artisan flutterwave:install --react   # or --vue (Vue 3) or --svelte (Svelte 5)
```

This copies TypeScript files into `resources/js/components/flutterwave`, along with `flutterwave.css` and the fonts. They're yours to restyle, and they don't add an npm dependency. They work with Inertia 1, 2 and 3 and are safe to render with Inertia SSR. See [The Flutterwave look](#the-flutterwave-look) for styling. You get the same three pieces in each framework:

- **`FlutterwavePayButton`** opens the inline modal using the config from `inlineConfig()`. It loads Flutterwave's script for you, once.
- **`VirtualAccount`** shows a `dynamicFor()` account with a copy button and a countdown. It checks for the payment until your webhook listener marks the order paid.
- **`useFlutterwaveCheckout()`** (React and Vue) and `openCheckout(config, callbacks)` (all three) are for building your own checkout UI.

Pass both props from your controller:

```php
return Inertia::render('Checkout', [
    'order' => $order->only('id', 'paid'),
    'checkout' => Flutterwave::inlineConfig(['amount' => $order->total, 'email' => $order->email, 'tx_ref' => 'ORDER-'.$order->id]),
    'account' => Flutterwave::virtualAccounts()->dynamicFor($order->email, $order->total, 'ORDER-'.$order->id),
]);
```

**React**

```tsx
import { router } from '@inertiajs/react';
import { FlutterwavePayButton, VirtualAccount } from '@/components/flutterwave';

<FlutterwavePayButton config={checkout}>Pay with card</FlutterwavePayButton>

<VirtualAccount account={account} paid={order.paid} reloadOnly={['order']} onPaid={() => router.visit(`/orders/${order.id}`)} />
```

**Vue**

```vue
<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { FlutterwavePayButton, VirtualAccount, type FlutterwaveCheckoutConfig, type FlutterwaveVirtualAccount } from '@/components/flutterwave';

defineProps<{ order: { id: number; paid: boolean }; checkout: FlutterwaveCheckoutConfig; account: FlutterwaveVirtualAccount }>();
</script>

<template>
    <FlutterwavePayButton :config="checkout">Pay with card</FlutterwavePayButton>

    <VirtualAccount :account="account" :paid="order.paid" :reload-only="['order']" @paid="router.visit(`/orders/${order.id}`)" />
</template>
```

**Svelte**

```svelte
<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { FlutterwavePayButton, VirtualAccount } from '@/components/flutterwave';

    let { order, checkout, account } = $props();
</script>

<FlutterwavePayButton config={checkout}>Pay with card</FlutterwavePayButton>

<VirtualAccount {account} paid={order.paid} reloadOnly={['order']} onPaid={() => router.visit(`/orders/${order.id}`)} />
```

**Card payments.** By default, Flutterwave redirects to your `FLW_REDIRECT_URL` after payment, and that route verifies the payment on the server. To stay on the page, pass `onSuccess` (`@success` in Vue) and send the payment to your server to verify. Flutterwave ignores callbacks when a redirect URL is set, so the component drops the redirect URL when you pass `onSuccess`. `onClose` (`@close`) runs when the customer closes the modal:

```tsx
<FlutterwavePayButton
    config={checkout}
    onSuccess={(payment) => router.post(`/orders/${order.id}/verify`, { transaction_id: payment.transaction_id })}
    onClose={(incomplete) => incomplete && toast('Payment cancelled')}
/>
```

**Bank transfers.** While `paid` is false, `VirtualAccount` reloads the `reloadOnly` props every 5 seconds (`pollInterval`). It pauses while the tab is hidden and stops once the account expires. Your `ChargeCompleted` listener updates the order when the transfer arrives, and the next reload picks that up. Set `pollInterval` to `0` to turn checking off. It also takes `onExpired` (`@expired` in Vue), `paidMessage` and `expiredMessage`; Vue also has `#paid` and `#expired` slots.

**Styling.** The components use the shared Flutterwave stylesheet. A `class` or `className` you pass is added to the root element; to change colours, fonts or corners, override the tokens described in [The Flutterwave look](#the-flutterwave-look).

### Checksum (optional)

Turn on Flutterwave's checksum to have it reject any checkout whose amount, currency, email or reference was changed in the browser:

```dotenv
FLW_CHECKSUM=true
```

The package then adds a `payload_hash` to every hosted and inline checkout, including the Blade button and the React, Vue and Svelte components. It's off by default. Turn it on, make one test payment, and keep it on if the payment goes through. If Flutterwave shows a checksum error instead, turn it off again and [open an issue](https://github.com/bajoski34/Laravel/issues). For a checkout you build yourself, `Flutterwave\Payments\Support\Checksum::make($payload, $secretKey)` returns the hash. Keep verifying payments on your server either way.

### Confirming a payment

Flutterwave redirects the customer to `FLW_REDIRECT_URL` with `status`, `tx_ref` and `transaction_id`. Never trust those query parameters. Verify on the server instead:

```php
Route::get('/flutterwave/payment/callback', function (Request $request) {
    $order = Order::where('reference', $request->tx_ref)->firstOrFail();

    if ($request->transaction_id && Flutterwave::isSuccessful($request->transaction_id, $order->amount, $order->currency, $order->reference)) {
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
| `QuoteCompleted` | An FX quote changed status. `$event->isReady()` when you can trade it |
| `ChargebackUpdated` | A chargeback was raised, accepted, declined or lost. `$event->needsResponse()` for new ones |

Change the path, add middleware or turn the route off under `webhook` in `config/flutterwave.php`.

### Duplicate webhooks and queueing

Flutterwave retries a webhook until your app answers with a 2xx, so the same webhook can arrive more than once. The package fires each webhook's events only once. It remembers handled webhooks in your cache for 7 days, but only after they've been handled, so a webhook whose charge couldn't be verified still gets through when Flutterwave retries. If two copies arrive at the same moment, one waits, and Flutterwave retries the other.

Use a cache store that every server shares and that survives restarts, such as redis or database. The `array` store forgets everything between requests. Set `FLW_WEBHOOK_DEDUPE_STORE` to use a different store from your default.

To answer Flutterwave immediately and handle webhooks in the background, set a queue:

```dotenv
FLW_WEBHOOK_QUEUE=payments
FLW_WEBHOOK_QUEUE_CONNECTION=redis   # optional, defaults to your default connection
```

The queued job retries for about four hours when a charge can't be verified yet. Keep a worker running: `php artisan queue:work --queue=payments`.

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

`resolveAccount()` works for Nigerian and Ghanaian bank accounts and Ghanaian mobile money numbers. Before a wallet-to-wallet transfer, use `resolveMerchant()` to check a Flutterwave merchant ID:

```php
Flutterwave::banks()->resolveAccount('0690000032', '044');                // Nigerian bank account
Flutterwave::banks()->resolveAccount('0031625807099', 'GH190101', 'GH');  // Ghanaian bank account
Flutterwave::banks()->resolveAccount('233542773934', 'MTN', 'GH');        // Ghanaian mobile money: phone number + provider code
Flutterwave::banks()->resolveMerchant('7981223');                         // Flutterwave merchant ID
```

With test keys, only [Flutterwave's test accounts](https://developer.flutterwave.com/docs/testing#bank-accounts) resolve. Real account numbers return an error.

### Testing transfers in the sandbox

With test keys, Flutterwave leaves transfers `PENDING` unless the reference says how they should end. Pass `test_outcome` and the package adds the suffix Flutterwave looks for. With live keys it's ignored:

```php
Flutterwave::transfers()->create([
    'account_bank' => '044',
    'account_number' => '0690000031',
    'amount' => 5500,
    'currency' => 'NGN',
    'test_outcome' => 'success',  // or 'failure'
    'test_resolve_after' => 1,    // minutes; Flutterwave's default is 10
]);
```

Sandbox transfers also need your server's IP whitelisted on the dashboard. Transfers to `0690000036` fail as a blacklisted account.

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

## Saved cards

After a customer pays by card once, you can charge that card again without them entering it. Add the columns and the trait:

```shell
php artisan flutterwave:install --billable
php artisan migrate
```

```php
use Flutterwave\Payments\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

Save the card when the first payment comes in, then charge it whenever you need to:

```php
Event::listen(function (ChargeCompleted $event) {
    if ($event->isSuccessful() && ($event->transaction['payment_type'] ?? null) === 'card') {
        User::where('email', $event->customerEmail())->first()?->saveFlutterwaveCard($event->transaction);
    }
});

$user->flutterwaveCard()?->label();        // "MASTERCARD ending 2950"
$charge = $user->chargeSavedCard(5000, ['narration' => 'October plan']);
```

- **Token storage:** the card token is stored encrypted, and hidden from `toArray()` and JSON, so it never reaches the browser in an Inertia prop.
- **Expiry:** tokens are tied to the email the card was saved with and last a year. `$user->flutterwaveCard()->expiresWithin(30)` tells you when to ask the customer to pay by card again.
- **3-D Secure:** repeat charges use 3-D Secure unless Flutterwave has enabled no-auth charges on your account, so check `$charge['data']['status']`. A `pending` charge is waiting for the customer to approve it, and its webhook arrives when they do.

Without the trait, call the service directly: `Flutterwave::cards()->charge($token, $email, 5000)`. `chargeMany()` charges many cards in one request, which needs no-auth charges enabled.

### Pre-authorization

Hold an amount on a saved card now, and charge it later, for example when a ride ends or a guest checks out. Flutterwave has to enable this on your account.

```php
$hold = $user->preauthorizeSavedCard(20000);
$ref = $hold['data']['flw_ref'];

Flutterwave::preauth()->capture($ref, 15000);  // charge part or all of it, once or several times
Flutterwave::preauth()->void($ref);            // or release the hold
Flutterwave::preauth()->refund($ref, 5000);    // give back money you captured
```

Holds you don't capture are released after 7 days.

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

### Dynamic accounts for one-off payments

A dynamic account is a single-use NGN or GHS account number for one payment. It expires after an hour by default. Drop the component into your checkout page:

```blade
<x-flutterwave-virtual-account
    :amount="$order->total"
    :email="$order->customer_email"
    :tx-ref="'ORDER-'.$order->id"
    :expires="1800"
    narration="Jane's Store"
/>
```

It shows the bank name, the account number in a copy field, the amount to send and a live countdown. When the account expires, it shows a message instead. Anything between the tags is rendered under the account details, and `class` is added to the wrapper.

- **Always pass a `tx-ref`.** The account is cached for that reference until it expires, so a page refresh shows the same account number. Without a `tx-ref`, every render creates a new account.
- Optional attributes: `currency` (`NGN` or `GHS`), `expires` (seconds: 60 up to 2 months for NGN, 48 hours for GHS), `firstname`, `lastname`, `phone`, `narration`, `bank-code` and `expired-message`.
- Already created the account in a controller? Pass the response instead, and no API call is made: `<x-flutterwave-virtual-account :account="$response" />`.

Using Inertia, or creating the account in a controller? `dynamicFor()` uses the same per-`tx_ref` caching as the component and returns a flat array that works as a prop:

```php
$account = Flutterwave::virtualAccounts()->dynamicFor($order->email, $order->total, 'ORDER-'.$order->id, ['expires' => 1800]);

// ['account_number' => '9519802749', 'bank_name' => 'Flutterwave MFB', 'amount' => '5070.00', 'currency' => 'NGN',
//  'tx_ref' => 'ORDER-42', 'expires_at' => '2026-10-14T12:30:00+00:00', 'expires_in' => 1800, 'note' => '...', ...]

return Inertia::render('Checkout', ['account' => $account]);
```

Base your frontend countdown on `expires_in`, not `expires_at`, so a wrong clock on the customer's device doesn't matter. The Blade component also accepts this array: `<x-flutterwave-virtual-account :account="$account" />`. For a single uncached account, use `dynamic($email, $amount, $options)`, which returns Flutterwave's raw response.

When the customer pays, Flutterwave sends a `charge.completed` webhook with `payment_type: bank_transfer`, so your existing `ChargeCompleted` listener handles it. NGN accounts reverse an underpayment, and also any amount paid over the total. GHS accounts reject anything that isn't the exact amount.

## Wallets (payout subaccounts)

Give each customer a wallet: a balance on your Flutterwave account with its own bank account number, so anyone can pay into it.

```php
$wallet = Flutterwave::payoutSubaccounts()->create('Jane Doe', 'jane@example.com', 'NG', '08100000000');
$reference = $wallet['data']['account_reference'];   // store this on the customer

$wallet['data']['nuban'];                            // the account number to fund it
Flutterwave::payoutSubaccounts()->balance($reference);
Flutterwave::payoutSubaccounts()->transactions($reference, '2026-10-01', '2026-10-31');
```

`all()`, `find()`, `update()`, `staticAccount()` and `delete()` cover the rest. Deleting deactivates the wallet in the background.

## Chargebacks

When a customer disputes a payment with their bank, respond before the due date: 10 working hours for local transactions, 30 for international ones. If you don't, the chargeback is lost and the customer is refunded.

```php
$open = Flutterwave::chargebacks()->all(['status' => 'initiated']);
Flutterwave::chargebacks()->find($flwRef);  // one chargeback with its history

// The customer didn't get what they paid for: refund them
Flutterwave::chargebacks()->accept($chargebackId, 'Order was never shipped.');

// You have evidence they did: upload it and dispute
Flutterwave::chargebacks()->declineWithProof($chargebackId, 'Delivered on 3 October and signed for.', $request->file('proof'));
```

Proof must be one JPEG, PNG or PDF file under 8MB, so merge multiple documents into one first. `declineWithProof()` accepts a file path or an uploaded file, and checks it before sending anything. To do it in two steps, call `uploadProof($file)` to get a proof link, then `decline($id, $comment, $proofLink)`.

To have Flutterwave notify you by webhook instead of email, ask them to enable chargeback webhooks. Each one fires `ChargebackUpdated`:

```php
Event::listen(function (ChargebackUpdated $event) {
    if ($event->needsResponse()) {
        Notification::route('mail', 'disputes@shop.test')->notify(new ChargebackRaised($event->data));
    }
});
```

## FX trades

Convert between currencies at a quoted rate. Supported pairs are NGN to USD, GHS to USD and USD to NGN. FX has to be enabled on your account, trades run on weekdays only, and the minimum is USD 1,000 or its equivalent.

```php
$quote = Flutterwave::fx()->quote('NGN', 'USD', 2_000_000, 'FX-ORDER-7');  // spend NGN 2,000,000 on dollars

// When the quote is ready (usually seconds later), check the rate and trade it
$ready = Flutterwave::fx()->findQuote($quote['data']['id'])['data'];
// $ready['status'] === 'READY'; $ready['quote']['best_available_rate'], ['approved_quantity'], ['total_value']

$trade = Flutterwave::fx()->tradeIfReady($ready['id'], 'Supplier payment');
Flutterwave::fx()->findTrade($trade['data']['id']);  // NEW, PENDING, then SETTLED or FAILED
```

A quote is valid for 5 minutes and can only be traded once. `tradeIfReady()` re-checks the quote first, so an expired one fails with a clear message instead of at the API. `approved_quantity` can be lower than the amount you asked for, depending on liquidity and your account limits. Instead of polling, listen for `QuoteCompleted`, and for `TransferCompleted` when the trade settles.

## Bill payments

Pay for airtime, data, electricity, cable TV and more from your Flutterwave balance. Find the codes first. `php artisan flutterwave:bills` walks you through them, or do it in code:

```php
Flutterwave::bills()->categories();          // AIRTIME, MOBILEDATA, CABLEBILLS, UTILITYBILLS...
Flutterwave::bills()->billers('CABLEBILLS'); // DSTV is BIL119
Flutterwave::bills()->items('BIL119');       // DSTV Premium is CB140, with its price and fee
```

Bill payments can't be undone, so check the customer's meter, smartcard or phone number first. `payValidated()` validates it, checks the amount against the provider's limits, and only then pays:

```php
$payment = Flutterwave::bills()->payValidated('BIL119', 'CB140', $smartcardNumber, 14600);

Flutterwave::bills()->status($payment['data']['tx_ref']);
Flutterwave::bills()->history(['status' => 'Failed']);
```

`validate()` on its own returns the name on the account, so you can show it to the customer to confirm.

## BVN verification

Check a Nigerian customer's Bank Verification Number, with their consent, before giving them a static virtual account or a wallet:

```php
$consent = Flutterwave::bvn()->requestConsent($bvn, $firstName, $lastName, route('kyc.done'));
// Store $consent['data']['reference'], then send the customer to $consent['data']['url'] to consent.

// When they come back:
Flutterwave::bvn()->matches($reference, $firstName, $lastName);  // true once consented and the names match
```

The full result from `verification($reference)` includes personal data, including a photo of the customer's face. Keep only what you need, and don't log it. The package never logs successful responses.

## Admin components for chargebacks and FX

Drop-in screens for your team: a chargeback list where you can accept or dispute each chargeback, and an FX converter that gets a rate, trades it and follows the trade. They come in Blade, React, Vue and Svelte. The Inertia versions are published with the rest of the components (`flutterwave:install --react`, `--vue` or `--svelte`).

These screens refund customers and move money, so they're off until you turn them on in two places.

**1. Register the routes inside your own admin group.** Nothing is registered until you call `Route::flutterwaveAdmin()`:

```php
// routes/web.php
Route::middleware(['auth', 'verified'])->prefix('admin')->group(function () {
    Route::flutterwaveAdmin();  // POST admin/flutterwave/chargebacks/{id}/accept|decline, admin/flutterwave/fx/quotes|trades
});
```

**2. Say who may use them.** Every action checks the `flutterwave.manage` ability. Until you define it, every request is refused with a 403, even for signed-in users:

```php
// AppServiceProvider::boot()
Gate::define('flutterwave.manage', fn (User $user) => $user->is_admin);
```

Then give your page the panel data. `panel()` returns everything the component needs, including the countdowns and the action URLs. API errors come back in `panel['error']` instead of being thrown:

```php
return Inertia::render('Admin/Payments', [
    'chargebacks' => Flutterwave::chargebacks()->panel(['status' => 'initiated']),
    'fx' => Flutterwave::fx()->panel($request->query('fx_quote'), $request->query('fx_trade')),
]);
```

```tsx
import { Chargebacks, FxConverter } from '@/components/flutterwave';

<Chargebacks panel={chargebacks} />
<FxConverter panel={fx} reloadOnly={['fx']} />
```

In Blade, pass the same arrays: `<x-flutterwave-chargebacks :panel="$chargebacks" />` and `<x-flutterwave-fx :panel="$fx" />`. The Blade versions work without JavaScript, and use a required checkbox in place of the confirm step.

- **Chargebacks** shows how long is left to respond, highlighted when it's under two hours, plus the customer's reason and the history. Disputing uploads one JPEG, PNG or PDF under 8MB, checked in the browser and again on the server. Accepting asks for a reason. Without the routes, the list is read-only.
- **FX converter** walks through each step: request a rate, wait while it's priced, review the rate with a 5-minute countdown, confirm the trade, then watch it until it settles. While a quote or trade is pending, it checks for updates by itself. The quote and trade IDs are kept in the `fx_quote` and `fx_trade` query parameters, so a page reload keeps your place. If Flutterwave approves less than you asked for, the converter says so before you trade.

### Transactions, refunds, settlements and beneficiaries

Four more admin components, with the same setup as the ones above. Each `panel()` takes the request's query string, so filters, page numbers and the item being viewed live in the URL:

```php
return Inertia::render('Admin/Money', [
    'transactions' => Flutterwave::transactions()->panel($request->query()),
    'refunds' => Flutterwave::refunds()->panel($request->query()),
    'settlements' => Flutterwave::settlements()->panel($request->query()),
    'beneficiaries' => Flutterwave::beneficiaries()->panel($request->query()),
]);
```

```tsx
import { Beneficiaries, Refunds, Settlements, Transactions } from '@/components/flutterwave';

<Transactions panel={transactions} reloadOnly={['transactions']} />
```

In Blade: `<x-flutterwave-transactions :panel="$transactions" />`, and the same for `refunds`, `settlements` and `beneficiaries`.

- **Transactions** filters by date, status, customer email and reference. For each payment you can see its timeline, resend its webhook, or refund it in full or in part with a reason, after a confirmation step. A fee checker shows what Flutterwave charges on an amount.
- **Refunds** lists refunds with their status, transaction and reason.
- **Settlements** lists payouts with their net amount and any problem Flutterwave flagged, such as a missing settlement account. Open one to see what was collected, the fees, refunds and chargebacks, and the payments it included.
- **Beneficiaries** adds recipients with a bank dropdown and deletes them. Leave the name empty to use the name on the account.

Refunds, webhook resends and beneficiary changes are written to the audit log.

### Bank dropdown

`BankSelect` lists a country's banks by name and submits the bank code, with a search box when the list is long. Nigeria alone has hundreds of banks.

```blade
<x-flutterwave-bank-select name="account_bank" :selected="old('account_bank')" />
<x-flutterwave-bank-select country="GH" />
```

The Blade component loads the list itself. In React, Vue and Svelte, pass the banks from `Flutterwave::banks()->options($country)` as a prop:

```tsx
<BankSelect banks={banks} value={bank} onChange={setBank} />    // React
<BankSelect v-model="bank" :banks="banks" />                    // Vue (3.4+)
<BankSelect banks={banks} bind:value={bank} />                  // Svelte
```

The list is cached for a day, since bank lists rarely change.

### Ready-made admin page

`Route::flutterwaveAdmin()` also registers a page at its prefix (`/admin/flutterwave` in the example above). Its Overview shows your wallet balances, the chargebacks that need a response (with an "All" tab), and the FX converter. Transactions, Refunds, Settlements and Beneficiaries each have their own section, and only the section you're viewing is loaded. It checks the same `flutterwave.manage` ability. To change it, publish the views with `php artisan vendor:publish --tag=flutterwave-views` and edit `admin/index.blade.php`. To register only the actions and use your own page, call `Route::flutterwaveAdmin(page: false)`.

## Notifications

Chargebacks have deadlines, and failed payouts mean someone wasn't paid. Set addresses to hear about both by email:

```dotenv
FLW_NOTIFY_CHARGEBACKS=disputes@shop.test,owner@shop.test
FLW_NOTIFY_FAILED_TRANSFERS=finance@shop.test
```

The chargeback email says how long you have and links to the admin page when it's registered. To send the notifications yourself, for example to Slack or the database, use the classes directly:

```php
use Flutterwave\Payments\Notifications\ChargebackRaised;

$admin->notify(new ChargebackRaised($event->data, ['mail', 'database']));
```

`TransferFailed` works the same way with a transfer from `TransferCompleted`.

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
Flutterwave::transactions()->all(['status' => 'successful', 'from' => '2026-10-01']);  // last 30 days when no dates are given
Flutterwave::transactions()->timeline($transactionId);    // every step the customer took
Flutterwave::transactions()->fee(10000, 'NGN', 'card');   // what Flutterwave charges on a payment
Flutterwave::transactions()->resendFailedHooks($transactionId);

Flutterwave::refund($transactionId);                                  // full refund
Flutterwave::refund($transactionId, 1000, ['comments' => 'Damaged']); // partial, with a reason
Flutterwave::refunds()->all(['status' => 'completed']);
Flutterwave::refunds()->find($refundId);

Flutterwave::settlements()->all(['from' => '2026-10-01']);
Flutterwave::settlements()->find($settlementId);          // includes the transactions it paid out

Flutterwave::beneficiaries()->create('0690000032', '044', 'Jane Doe');
Flutterwave::beneficiaries()->all();
Flutterwave::beneficiaries()->delete($beneficiaryId);

Flutterwave::banks()->options('NG');   // [['code' => '044', 'name' => 'Access Bank'], ...] for a dropdown, cached for a day
Flutterwave::balances()->currency('NGN');
```

Every method returns Flutterwave's JSON response as an array (`status`, `message`, `data` and, for lists, `meta`).

## Artisan commands

Everything the standalone [Flutterwave CLI](https://github.com/Flutterwave/CLI) does, using the keys already in your `.env`:

```shell
php artisan flutterwave:doctor                          # check keys, webhooks, cache, queue and API access
php artisan flutterwave:verify 4975363                  # transaction status by id
php artisan flutterwave:verify ORDER-42 --ref           # ...or by tx_ref
php artisan flutterwave:balance --currency=NGN          # wallet balances
php artisan flutterwave:banks GH --search=ecobank       # bank codes
php artisan flutterwave:branches 280                    # branches for a bank id
php artisan flutterwave:resolve 0690000032 044          # account name lookup
php artisan flutterwave:resolve 233542773934 MTN --country=GH   # Ghanaian bank or mobile money
php artisan flutterwave:resolve 7981223 --merchant      # Flutterwave merchant ID
php artisan flutterwave:bin 539983                      # card issuer, type and country
php artisan flutterwave:rate --amount=100 --from=USD --to=NGN
php artisan flutterwave:pay jane@example.com 100 --currency=NGN --open
php artisan flutterwave:resend-webhook 4975363          # resend a failed webhook
php artisan flutterwave:ping-webhook 4975363 http://localhost:8000/flutterwave/webhook

php artisan flutterwave:chargebacks --status=initiated   # disputes waiting for your response
php artisan flutterwave:chargebacks --ref=FLW-MOCK-c23b  # one chargeback and its history
php artisan flutterwave:accept-chargeback 1599 --comment="Order was never shipped"
php artisan flutterwave:decline-chargeback 1599 --proof=storage/disputes/1599.pdf --comment="Signed for on delivery"

php artisan flutterwave:fx-quote NGN USD 2000000         # waits until the rate is ready
php artisan flutterwave:fx-trade 570e21b4 --narration="Supplier payment"
php artisan flutterwave:fx-status 08e27e03 --trade

php artisan flutterwave:bills                           # bill categories, then: flutterwave:bills CABLEBILLS, flutterwave:bills --biller=BIL119
```

Run `flutterwave:doctor` after installing and before going live. It flags test and live keys mixed together, an app running on an IP address (where the checkout won't load), a missing webhook secret hash (which makes every webhook get rejected), a webhook URL Flutterwave can't reach, a cache that won't remember handled webhooks, and admin routes without the `flutterwave.manage` ability. It then checks that Flutterwave accepts your secret key. Pass `--offline` to skip that last check.

Commands that refund a customer or move money (`accept-chargeback`, `decline-chargeback` and `fx-trade`) show what will happen and ask before going ahead. Pass `--force` to run them from scripts or CI. Without it, they change nothing when there's no terminal to ask in.

`flutterwave:ping-webhook` fetches the real transaction (or transfer, with `--event=transfer`) and posts it to your local URL as a signed `charge.completed` or `transfer.completed` webhook, so you can test your listeners without exposing localhost. The URL defaults to this app's webhook route, and the signature uses `FLW_SECRET_HASH` unless you pass `--hash`.

## Audit log

Accepting or disputing a chargeback, trading FX, refunding a payment, resending a webhook, and adding or deleting a beneficiary, through the admin screens or the Artisan commands, are recorded in the `flutterwave` log channel. Each entry has the action, whether it succeeded, the user ID (or the system user for commands), and the IP address. The admin page is sent with `Cache-Control: no-store`, so browsers and proxies don't keep copies of balances and disputes.

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

`Flutterwave::fake()` answers every Flutterwave API call with a realistic response, and records what your app sent. It only fakes Flutterwave, so your other `Http::fake()` calls keep working:

```php
use Flutterwave\Payments\Facades\Flutterwave;

it('starts a checkout for the order total', function () {
    $flutterwave = Flutterwave::fake();

    $this->post('/orders/42/pay')->assertRedirect();

    $flutterwave->assertCheckoutCreated(fn (array $payment) => $payment['amount'] === 5000);
});
```

Verifying a transaction only succeeds for ones you register, so a test can't pass by accident:

```php
Flutterwave::fake()->transaction(4975363, ['amount' => 5000, 'currency' => 'NGN', 'tx_ref' => 'ORDER-42']);
```

- **Failures:** `failing('POST', 'transfers', 'Insufficient wallet balance')` makes a call fail the way Flutterwave does. `respond('GET', 'transfers/*', [...])` answers an endpoint yourself.
- **Assertions:** `assertCheckoutCreated()`, `assertTransferSent()`, `assertCardCharged()`, `assertRefunded($id, $amount)`, `assertBillPaid()`, `assertSent($method, $path, $callback)`, `assertNotSent()` and `assertNothingSent()`.
- **Webhooks:** post your test payload with `FlutterwaveFake::webhookHeaders()` to pass the signature check. Registered transactions are verified the same way they are in production:

```php
use Flutterwave\Payments\Testing\FlutterwaveFake;

$this->postJson('/flutterwave/webhook', ['event' => 'charge.completed', 'data' => ['id' => 4975363]], FlutterwaveFake::webhookHeaders());
```

The fake fills in placeholder keys when your test environment has none.

For trying flows by hand against Flutterwave's sandbox, `Flutterwave\Payments\Testing\SandboxData` holds the official test values. These include cards that succeed or fail in specific ways, the special OTPs, the Access Bank test accounts and the blacklisted one, the DSTV and airtime bill credentials, and the BVN test identities. In test mode, airtime numbers must start with `+234`, and the checkout only loads on `localhost` or a domain, not on `127.0.0.1`. To test against a running app instead, use `php artisan flutterwave:ping-webhook {transaction-id}`.

## Customising

- **Branding and defaults:** set the business name, logo, currency, country and payment methods in `config/flutterwave.php`.
- **Example routes and views:** publish them by tag, as described in [Publishing files](#publishing-files).
- **Swap a service:** map a key in `services`, e.g. `'transfers' => App\Payments\Transfers::class` (extend the original class), or set a key to `null` to disable that service.

## Upgrading from 2.2

Nothing to change in your code. Three things behave differently:

- **Webhooks fire once.** A webhook Flutterwave delivers twice no longer fires its events twice. Handled webhooks are remembered in your cache, so with more than one server, use a shared store such as redis or database (`FLW_WEBHOOK_DEDUPE_STORE`). Run `php artisan flutterwave:doctor` to check. Set `FLW_WEBHOOK_DEDUPE=false` to keep the old behaviour.
- **Simultaneous webhooks.** If two copies of a webhook arrive at once, the second gets a `409`, and Flutterwave retries it later.
- **`Flutterwave::redirect()` on Inertia requests** returns a `409` with `X-Inertia-Location` instead of a redirect, so its return type is now `RedirectResponse|Response`. If a controller declares `: RedirectResponse` and returns it, widen the return type.

If you've published `config/flutterwave.php`, the new options (`checksum`, `notify`, and the webhook `dedupe` and `queue` settings) use their defaults until you add them. Compare with `php artisan vendor:publish --tag=flutterwave-config --force` in a scratch branch.

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
- [Flutterwave error reference](https://developer.flutterwave.com/docs/common-errors)
- Email the developer experience team at [developers@flutterwavego.com](mailto:developers@flutterwavego.com)
- Follow [@FlutterwaveEng](https://twitter.com/FlutterwaveEng)

## License

MIT. See [LICENSE](LICENSE). Copyright (c) Flutterwave Inc.
