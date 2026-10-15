# Events

All events live in `Flutterwave\Payments\Events` and fire from verified [webhooks](/guide/webhooks), once per webhook even when Flutterwave retries.

## WebhookReceived

Fires for every verified webhook, before the specific event below.

| Property | Type | |
| --- | --- | --- |
| `$event` | `string` | e.g. `charge.completed` |
| `$data` | `array` | The payload's `data` |
| `$payload` | `array` | The full payload |

## ChargeCompleted

`charge.completed`: a payment finished, successfully or not. Covers checkout, saved cards, virtual accounts and subscription renewals.

| Member | |
| --- | --- |
| `$transaction` | The transaction as re-fetched from the API (when `verify_charges` is on). Trust this one. |
| `$data` | The webhook body as sent |
| `$verified` | `true` when `$transaction` came from the API |
| `isSuccessful()` | Status is `successful` |
| `reference()` | The `tx_ref` |
| `amount()`, `currency()`, `customerEmail()` | From `$transaction` |

## TransferCompleted

`transfer.completed`: a payout succeeded or failed.

| Member | |
| --- | --- |
| `$data` | The transfer |
| `isSuccessful()` | Status is `SUCCESSFUL` |
| `reference()` | Your transfer reference |

## SubscriptionCancelled

`subscription.cancelled`. `$data` holds the subscription.

## QuoteCompleted

`quote.completed`, whenever an FX quote changes status. Despite the name, the quote may be `PROCESSING`, `READY`, `EXPIRED` or `FAILED`.

| Member | |
| --- | --- |
| `$data` | The quote |
| `isReady()` | You can trade it now |
| `quoteId()` | |

## ChargebackUpdated

`chargeback.initiated`, `.accepted`, `.declined` and `.lost`. Ask Flutterwave to enable chargeback webhooks for your account.

| Member | |
| --- | --- |
| `$event` | The webhook event name |
| `$data` | The chargeback |
| `status()` | Current status |
| `needsResponse()` | You still need to accept or decline it |

## Notifications

Two mail notifications ship with the package and are sent automatically when you set the addresses:

| Notification | Sent when | Config |
| --- | --- | --- |
| `ChargebackRaised` | A chargeback needs a response | `FLW_NOTIFY_CHARGEBACKS` |
| `TransferFailed` | A payout failed | `FLW_NOTIFY_FAILED_TRANSFERS` |

Both live in `Flutterwave\Payments\Notifications`, so you can also send them to your own notifiables.
