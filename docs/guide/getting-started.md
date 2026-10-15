# Getting started

The package gives your Laravel app the whole Flutterwave v3 API behind one facade, plus a webhook route, Laravel events, artisan commands and frontend components.

## Requirements

- PHP 8.1 or newer
- Laravel 9, 10, 11, 12 or 13
- A Flutterwave account. Test keys are on the dashboard under **Settings > API keys**.

## Install

```bash
composer require abraham-flutterwave/laravel-payment
```

Laravel discovers the service provider and the `Flutterwave` facade on its own. Then run the installer:

```bash
php artisan flutterwave:install
```

It publishes `config/flutterwave.php`, adds the `FLW_*` keys to your `.env`, and prints the webhook URL to paste into your dashboard. Add a flag for anything else you need:

| Flag | Publishes |
| --- | --- |
| `--react`, `--vue` or `--svelte` | Inertia components to `resources/js/components/flutterwave` ([guide](/guide/inertia)) |
| `--billable` | A migration for saving customers' cards ([guide](/guide/saved-cards)) |
| `--force` | Overwrites files you already published |

## Add your keys

Use the builder below, or fill the keys in by hand. The secret hash is any long random string: type the same value into **Settings > Webhooks > Secret hash** on the dashboard.

<EnvBuilder />

::: tip Test and live keys
Test keys contain `_TEST`. Keep both keys from the same mode, or `flutterwave:doctor` will flag it.
:::

## Check your setup

```bash
php artisan flutterwave:doctor
```

The doctor checks your keys, the webhook secret hash and URL, the cache used to dedupe webhooks, the webhook queue, the admin routes and, unless you pass `--offline`, that Flutterwave accepts your secret key.

## Take a test payment

From the terminal:

```bash
php artisan flutterwave:pay customer@example.com 5000 --open
```

Or from a controller:

```php
use Flutterwave\Payments\Facades\Flutterwave;

Route::post('/pay', fn () => Flutterwave::redirect([
    'amount' => 5000,
    'email' => auth()->user()->email,
]));
```

Pay with one of Flutterwave's test cards (see **Testing** in the [Flutterwave docs](https://developer.flutterwave.com)), then head to [Verifying payments](/guide/verifying-payments) to confirm it on your server.

## Next steps

- [Checkout](/guide/checkout): hosted page, inline modal and Inertia
- [Webhooks](/guide/webhooks): react to payments with Laravel events
- [Service explorer](/reference/services): everything else the package can do
