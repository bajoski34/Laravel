# Configuration

Everything lives in `config/flutterwave.php`, and nearly every option reads from an `FLW_*` environment variable, so most apps never edit the file. See [Config options](/reference/config) for the full list.

## Business details

These fill in the checkout's title, description and logo, and the defaults for country and currency.

```dotenv
FLW_BUSINESS_NAME="Acme Stores"
FLW_PAYMENT_DESCRIPTOR="Acme Stores"
FLW_CHECKOUT_DESCRIPTION="Thanks for shopping with us"
FLW_BUSINESS_LOGO=https://acme.test/logo.png
FLW_DEFAULT_COUNTRY=GH
FLW_DEFAULT_CURRENCY=GHS
FLW_TRANSACTION_PREFIX=ACME-
```

Payment methods shown at checkout come from the `paymentType` array in the config file. Pass `payment_options` in a single checkout to override it.

## Redirect URLs

After paying, Flutterwave sends the customer to `FLW_REDIRECT_URL` (by default `APP_URL/flutterwave/payment/callback`). Publish the example routes to get a callback that re-verifies the payment:

```bash
php artisan vendor:publish --tag=flutterwave-routes
```

## HTTP client

```dotenv
FLW_TIMEOUT=60   # seconds
FLW_RETRIES=2    # GET requests only; POSTs are never retried
```

## Swapping or turning off services

Every service ships enabled. Map a key to your own class to replace it, or to `null` to turn it off:

```php
// config/flutterwave.php
'services' => [
    'transfers' => App\Payments\AuditedTransfers::class,
    'stablecoins' => null,
],
```

Your class receives the same `(Api $api, array $config)` constructor arguments as the built-in one.

## Checksum

```dotenv
FLW_CHECKSUM=true
```

Adds a `payload_hash` to every checkout so Flutterwave rejects one whose amount, currency, email or reference was changed in the browser. It's off by default: turn it on, make a test payment, and keep it on if it succeeds. Verify payments on your server either way.

## Logging

The package logs to a `flutterwave` channel. Add one to `config/logging.php` to keep those lines in their own file:

```php
'flutterwave' => [
    'driver' => 'single',
    'path' => storage_path('logs/flutterwave.log'),
    'level' => 'debug',
],
```
