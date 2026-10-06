# Changelog

## 2.2.0

### Added
- `Flutterwave::checkout()` and `Flutterwave::redirect()`: start a hosted payment with just an amount and an email. `tx_ref`, currency, redirect URL and branding are filled in from config.
- `Flutterwave::isSuccessful($id, $amount, $currency)`: verify a payment and check that the amount and currency match in one call.
- `Flutterwave::refund($id, $amount = null)`.
- New services: `transfers()`, `banks()`, `subaccounts()`, `plans()`, `subscriptions()`, `virtualAccounts()`, `beneficiaries()`, `balances()`, `refunds()` and `settlements()`.
- Automatic webhook route (`POST /flutterwave/webhook`) with signature verification (`verif-hash` and HMAC `flutterwave-signature`) and Laravel events: `WebhookReceived`, `ChargeCompleted`, `TransferCompleted` and `SubscriptionCancelled`. Charges are re-verified with the API before `ChargeCompleted` fires.
- `stablecoins()` service: send USDC, USDT and RLUSD on Solana, Ethereum, Base and Polygon, fund stablecoin wallets from fiat, crypto fees and balances. Network, coin and address format are validated before any request is sent. New `FLW_MERCHANT_ID` config.
- `<x-flutterwave-button>` Blade component for inline checkout.
- Artisan commands: `flutterwave:install`, `flutterwave:verify` and `flutterwave:banks`.
- `FlutterwaveException`, with the HTTP status and Flutterwave response body, thrown by the new services.
- Configurable timeout and retries. Only GET requests are retried, so transfers are never sent twice.
- Laravel 11, 12 and 13 support, plus a CI matrix covering PHP 8.1 to 8.5.

### Fixed
- The `flutterwave` log channel is now registered automatically.
- `ConfirmRequest` and `PaymentRequest` crashed on failed validation.
- Webhook verification threw a `TypeError` when the signature header was missing, and now uses a constant-time comparison.
- `transactions()->fees()` called a malformed URL, and `resendFailedHooks()` called the wrong endpoint.
- The inline modal broke on payloads containing quotes and redirected cancelled payments to a callback that rejected them.
- The `Event` trait redirected to routes that did not exist.
- Package exceptions no longer crash when the example routes are not published.
- The test suite now runs (wrong `getPackageProviders` hook, missing routes).

### Changed
- Requires PHP 8.1+ and Laravel 9+. The previous constraint advertised PHP 7.4, which the code never supported.
- Published example routes now run in the `web` middleware group, so checkout forms need `@csrf`.
