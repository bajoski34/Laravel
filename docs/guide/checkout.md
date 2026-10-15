# Checkout

There are two ways to show Flutterwave's checkout:

- **Hosted page**: the customer leaves your site for a Flutterwave page, then comes back to your redirect URL.
- **Inline modal**: the checkout opens over your own page.

Both take the same payload. Only `amount` and an email are required; the package fills in `tx_ref`, `currency`, `redirect_url` and the customizations from your config.

## Build one

<CheckoutBuilder />

## Hosted page

```php
use Flutterwave\Payments\Facades\Flutterwave;

// Redirect straight away
return Flutterwave::redirect(['amount' => 5000, 'email' => $user->email]);

// Or just get the link, e.g. to email it
$link = Flutterwave::checkout(['amount' => 5000, 'email' => $user->email]);
```

`redirect()` also works for Inertia form submissions: for XHR requests it returns the `409` with an `X-Inertia-Location` header that `Inertia::location()` would, so the browser does a full page visit.

## Inline modal

```php
$payment_details = Flutterwave::render('inline', [
    'amount' => 5000,
    'email' => $user->email,
]);

return view('flutterwave::modal', compact('payment_details'));
```

The package's `modal` view loads Flutterwave's script and opens the checkout. For Inertia apps, use [`inlineConfig()` and the pay button components](/guide/inertia) instead.

## The payload

| Key | Notes |
| --- | --- |
| `amount` | Required, greater than zero |
| `email` or `customer` | `customer` is an array with `email`, `name`, `phone_number`. Pass one or the other. |
| `currency` | Defaults to `FLW_DEFAULT_CURRENCY` |
| `tx_ref` | Defaults to a unique reference with your prefix, like `LARAVEL-aB3xQ1729000000`. Pass your own order reference so you can match it later. |
| `redirect_url` | Defaults to `FLW_REDIRECT_URL` |
| `meta` | Any extra data; it comes back on the transaction and in webhooks |
| `payment_options` | Comma-separated, e.g. `card,banktransfer`. Defaults to `paymentType` in the config. |
| `subaccounts` | Split the payment, e.g. `[['id' => 'RS_...']]` |
| `customizations` | Merged over the title, description and logo from the config |

Anything else in Flutterwave's [standard checkout](https://developer.flutterwave.com) payload is passed through as is.

## Example routes

Publish working checkout, callback, success, failure and cancel routes to start from:

```bash
php artisan vendor:publish --tag=flutterwave-routes
```

They land in `routes/vendor/flutterwave/web.php`. Edit them freely.

::: warning Don't fulfil orders from the redirect
The status in the redirect's query string comes from the browser and can be changed. Always [verify on your server](/guide/verifying-payments), or better, fulfil from the [`ChargeCompleted` webhook event](/guide/webhooks).
:::
