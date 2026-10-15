# Config options

`config/flutterwave.php`, published by `php artisan flutterwave:install`. Generate your `.env` lines with the [.env builder](/guide/getting-started#add-your-keys).

## Keys and environment

| Option | Env | Default | |
| --- | --- | --- | --- |
| `publicKey` | `FLW_PUBLIC_KEY` | | Needed for the inline checkout and pay buttons |
| `secretKey` | `FLW_SECRET_KEY` | | Needed for every API call |
| `encryptionKey` | `FLW_ENCRYPTION_KEY` | `''` | Encrypts payloads that Flutterwave requires encrypted |
| `secretHash` | `FLW_SECRET_HASH` | `''` | Signs webhooks. Without it, every webhook is rejected. |
| `env` | `FLW_ENVIRONMENT` | `staging` | |
| `merchantId` | `FLW_MERCHANT_ID` | | |

## Checkout

| Option | Env | Default |
| --- | --- | --- |
| `businessName` | `FLW_BUSINESS_NAME` | `Flutterwave Store` |
| `title` | `FLW_PAYMENT_DESCRIPTOR` | `Flutterwave Store` |
| `description` | `FLW_CHECKOUT_DESCRIPTION` | `Flutterwave Store Description` |
| `logo` | `FLW_BUSINESS_LOGO` | A placeholder image |
| `country` | `FLW_DEFAULT_COUNTRY` | `NG` |
| `currency` | `FLW_DEFAULT_CURRENCY` | `NGN` |
| `transactionPrefix` | `FLW_TRANSACTION_PREFIX` | `LARAVEL-` |
| `paymentType` | | All methods: card, account, banktransfer, ussd, mobile money... |
| `checksum` | `FLW_CHECKSUM` | `false` |
| `redirectUrl` | `FLW_REDIRECT_URL` | `APP_URL/flutterwave/payment/callback` |
| `successUrl` | `FLW_SUCCESS_URL` | `APP_URL/flutterwave/payment/success` |
| `cancelUrl` | `FLW_CANCEL_URL` | `APP_URL/flutterwave/payment/cancel` |

## HTTP client

| Option | Env | Default | |
| --- | --- | --- | --- |
| `timeout` | `FLW_TIMEOUT` | `60` | Seconds |
| `retries` | `FLW_RETRIES` | `2` | GET requests only. POSTs are never retried. |

## Webhooks

| Option | Env | Default | |
| --- | --- | --- | --- |
| `webhook.enabled` | `FLW_WEBHOOK_ENABLED` | `true` | Registers the route |
| `webhook.path` | `FLW_WEBHOOK_PATH` | `flutterwave/webhook` | |
| `webhook.middleware` | | `[]` | Extra middleware for the route |
| `webhook.verify_charges` | | `true` | Re-fetch charges before `ChargeCompleted` |
| `webhook.dedupe` | `FLW_WEBHOOK_DEDUPE` | `true` | |
| `webhook.dedupe_store` | `FLW_WEBHOOK_DEDUPE_STORE` | Your default cache store | Use one that's shared and persistent |
| `webhook.dedupe_for` | | One week | Seconds |
| `webhook.queue` | `FLW_WEBHOOK_QUEUE` | | Queue name; empty handles webhooks during the request |
| `webhook.queue_connection` | `FLW_WEBHOOK_QUEUE_CONNECTION` | Your default connection | |

## Notifications

| Option | Env | |
| --- | --- | --- |
| `notify.chargebacks` | `FLW_NOTIFY_CHARGEBACKS` | Comma-separated emails |
| `notify.failed_transfers` | `FLW_NOTIFY_FAILED_TRANSFERS` | Comma-separated emails |

## Services

| Option | Default | |
| --- | --- | --- |
| `services` | `[]` | Map a service key to your own class, or to `null` to turn it off. See [Configuration](/guide/configuration#swapping-or-turning-off-services). |

Service keys: `transactions`, `webhooks`, `modals`, `transfers`, `banks`, `subaccounts`, `plans`, `subscriptions`, `virtual_accounts`, `beneficiaries`, `balances`, `refunds`, `settlements`, `stablecoins`, `cards`, `chargebacks`, `fx`, `preauth`, `bills`, `payout_subaccounts`, `bvn`.
