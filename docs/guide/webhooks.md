# Webhooks

Flutterwave posts to your app whenever something happens: a payment completes, a transfer lands, a chargeback is raised. The package registers the route, checks the signature, re-verifies charges, ignores duplicates and turns each webhook into a Laravel event. You only write listeners.

## Set it up

1. Pick a secret hash (any long random string) and put it in `.env`:

   ```dotenv
   FLW_SECRET_HASH=choose-a-long-random-string
   ```

2. On the dashboard, open **Settings > Webhooks**, enter the same secret hash, and set the URL to:

   ```
   https://your-app.com/flutterwave/webhook
   ```

3. Exclude the route from CSRF. It's registered outside the `web` group, so on a default app there's nothing to do. If you wrapped it in `web` middleware yourself, add `flutterwave/webhook` to your CSRF exceptions.

Run `php artisan flutterwave:doctor` to check all of it.

## Listen for events

```php
use Flutterwave\Payments\Events\ChargeCompleted;
use Illuminate\Support\Facades\Event;

Event::listen(function (ChargeCompleted $event) {
    if (! $event->isSuccessful()) {
        return;
    }

    $order = Order::where('reference', $event->reference())->firstOrFail();

    // $event->transaction is fresh from the API, not the webhook body.
    if ($event->transaction['amount'] >= $order->total && $event->transaction['currency'] === $order->currency) {
        $order->markPaid();
    }
});
```

| Webhook | Event |
| --- | --- |
| `charge.completed` | `ChargeCompleted` |
| `transfer.completed` | `TransferCompleted` |
| `subscription.cancelled` | `SubscriptionCancelled` |
| `quote.completed` | `QuoteCompleted` |
| `chargeback.*` | `ChargebackUpdated` |
| every webhook | `WebhookReceived` |

See [Events](/reference/events) for each event's properties and helpers.

## What happens to each webhook

1. **Signature check.** `VerifyWebhookSignature` accepts either header Flutterwave sends: `flutterwave-signature` (base64 HMAC-SHA256 of the raw body, keyed with your secret hash) or the older `verif-hash` (the secret hash itself). Anything else gets a `401`. With no `FLW_SECRET_HASH` set, every webhook is rejected.
2. **Dedupe.** Flutterwave retries deliveries, so each webhook's event, id and status are remembered in your cache for a week, and repeats are acknowledged without firing events again.
3. **Re-verification.** For `charge.completed`, the transaction is fetched from the API before `ChargeCompleted` fires, so a forged payload can't mark an order paid. If that fails, the response is a `500` and Flutterwave retries later.
4. **Events.** `WebhookReceived` always fires, then the specific event.

## Try the signature

<WebhookTester />

## Queue it

Answer Flutterwave straight away and do the work on a queue:

```dotenv
FLW_WEBHOOK_QUEUE=webhooks
FLW_WEBHOOK_QUEUE_CONNECTION=redis   # optional
```

The job retries on failure. Keep a worker running: `php artisan queue:work --queue=webhooks`.

## Dedupe store

Dedupe needs a cache that's shared between servers and survives restarts. The `array` and `file` stores don't qualify on multi-server setups, so point it at Redis or the database:

```dotenv
FLW_WEBHOOK_DEDUPE_STORE=redis
```

## Test locally

Flutterwave can't reach `localhost`. Two options:

```bash
# Sign and send a webhook for a real test transaction to your local app
php artisan flutterwave:ping-webhook 4975363

# Ask Flutterwave to resend one that failed, to your dashboard URL
php artisan flutterwave:resend-webhook 4975363
```

`ping-webhook` defaults to this app's webhook route and `FLW_SECRET_HASH`. Use `--event=transfer` with a transfer id for transfer webhooks.

## Options

```php
// config/flutterwave.php
'webhook' => [
    'enabled' => env('FLW_WEBHOOK_ENABLED', true),
    'path' => env('FLW_WEBHOOK_PATH', 'flutterwave/webhook'),
    'middleware' => [],             // extra middleware for the route
    'verify_charges' => true,       // re-fetch charges before ChargeCompleted
    'dedupe' => env('FLW_WEBHOOK_DEDUPE', true),
    'dedupe_store' => env('FLW_WEBHOOK_DEDUPE_STORE'),
    'dedupe_for' => 7 * 24 * 3600,
    'queue' => env('FLW_WEBHOOK_QUEUE'),
    'queue_connection' => env('FLW_WEBHOOK_QUEUE_CONNECTION'),
],
```

## Email alerts

Get an email when a chargeback needs a response or a payout fails:

```dotenv
FLW_NOTIFY_CHARGEBACKS=ops@your-app.com
FLW_NOTIFY_FAILED_TRANSFERS=finance@your-app.com,cto@your-app.com
```
