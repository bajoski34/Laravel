# Changelog

All notable changes to `abraham-flutterwave/laravel-payment` are documented here.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project follows [Semantic Versioning](https://semver.org/).

## [2.2.0] - Unreleased

The biggest release so far: most of the Flutterwave v3 API, webhooks as Laravel events, and one-line checkout. Code written for 2.1 keeps working; see **Breaking** below for the platform requirements.

### Breaking
- Requires **PHP 8.1+** and **Laravel 9+**. The old constraint advertised PHP 7.4, but the code already needed 8.1, and Laravel 8 is end-of-life.
- Published example routes now run in the `web` middleware group, so forms posting to `/flutterwave/payment/checkout` need `@csrf`. Re-publish with `php artisan vendor:publish --tag=flutterwave-routes --force` to pick up the fixed callback.

### Added

**Payments**
- `Flutterwave::checkout()` returns a hosted payment link, and `Flutterwave::redirect()` sends the customer straight to it. Only `amount` and `email` are required; `tx_ref`, currency, redirect URL and branding come from config.
- `Flutterwave::isSuccessful($id, $amount, $currency)` verifies a payment and confirms the amount and currency in one call.
- `Flutterwave::refund($id, $amount = null)` for full and partial refunds.
- `<x-flutterwave-button>` Blade component for inline checkout.

**New API services**
- `transfers()`: single and bulk payouts, fees, FX rates, lookups and retries. A `reference` is generated when one isn't supplied.
- `stablecoins()`: send USDC, USDT and RLUSD on Solana, Ethereum, Base and Polygon, fund stablecoin wallets from NGN, USD, GBP, EUR or GHS, and check crypto fees and balances. The network, coin and wallet address format are validated before any request is sent, because crypto transfers can't be reversed.
- `banks()`: bank lists, branches and account-name resolution.
- `subaccounts()`: split payments for marketplaces.
- `plans()` and `subscriptions()`: recurring billing.
- `virtualAccounts()`: static and dynamic account numbers.
- `beneficiaries()`, `balances()`, `refunds()` and `settlements()`.

**Webhooks**
- Automatic `POST /flutterwave/webhook` route. Signatures are checked against `FLW_SECRET_HASH` using either the `verif-hash` header or the HMAC `flutterwave-signature` header.
- Laravel events: `WebhookReceived`, `ChargeCompleted`, `TransferCompleted` and `SubscriptionCancelled`.
- `charge.completed` transactions are re-fetched from the API before `ChargeCompleted` fires, so a forged payload can't mark an order as paid. If verification fails, the route responds with HTTP 500 so Flutterwave retries.
- `Webhooks::isValid()` checks either signature format.

**Developer experience**
- Artisan commands: `flutterwave:install` (publishes the config, adds keys to `.env` and prints the webhook URL), `flutterwave:verify` and `flutterwave:banks`.
- `FlutterwaveException`, carrying the HTTP status and the Flutterwave response body, is thrown by the new services. `NetworkConnection` now extends it.
- New config options: `timeout`, `retries`, `webhook` (enabled, path, middleware, verify_charges) and `merchantId` (`FLW_MERCHANT_ID`), plus the `FLW_TIMEOUT`, `FLW_RETRIES`, `FLW_WEBHOOK_ENABLED` and `FLW_WEBHOOK_PATH` env variables.
- Services can be replaced with your own class, or disabled by setting them to `null`, through `services` in the config.
- A `flutterwave-views` publish tag, alongside the new `flutterwave-config` and `flutterwave-routes` tags.
- Currency constants for EUR, GBP, ZMW, MWK, EGP, ETB, USDT, USDC and RLUSD, plus a `Stablecoin` class listing the supported networks and coins.
- Support for Laravel 11, 12 and 13 and PHP 8.5, with a CI matrix covering PHP 8.1 to 8.5 and Laravel 9 to 13.

### Changed
- All API calls go through a shared client with a configurable timeout. Only GET requests are retried on connection failures, so a timeout can never send a transfer twice.
- The User-Agent now reports the real package version.
- `Transactions`, `Modal` and `Webhooks` are no longer `final`, so they can be extended.
- `Transactions` keeps its 2.1 behaviour of returning the error body instead of throwing.
- Transaction references are generated with `random_int()` instead of `mt_rand()`.
- Exceptions only redirect to the error page when the `flutterwave.error` route exists. Otherwise Laravel's normal error handling applies.

### Fixed
- The `flutterwave` log channel was never registered. It is now added automatically.
- `ConfirmRequest` and `PaymentRequest` crashed on failed validation because of missing imports and a bad `InvalidArgument` call.
- Webhook verification threw a `TypeError` when the signature header was missing. It also now uses a constant-time comparison.
- `transactions()->fees()` called a malformed URL, and `resendFailedHooks()` called the wrong endpoint.
- The inline modal broke on payloads containing quotes and was open to script injection through payment data.
- Closing the inline modal redirected to a callback URL that then failed validation.
- The example callback route returned nothing for pending or failed payments and trusted the status in the query string.
- The `Event` trait redirected to routes that did not exist.
- Missing secret or public keys now raise a clear message naming the `.env` variable.
- The test suite never ran because of a misnamed `getPackageProviders` hook and a test against a non-existent route.

### Removed
- The unused `src/Data/Services.php`, which referenced a class that did not exist.

## [2.1.1] - 2025-07-27

### Changed
- Improved error messages and error handling for developers.

## [2.1.0] - 2025-02-11

### Added
- Example routes are published to `routes/vendor/flutterwave/web.php` and loaded automatically.

### Changed
- `PaymentRequest` validation updates.

## [1.0.5] - 2025-02-11

### Fixed
- Service provider and `Flutterwave` class fixes.

## [1.0.4] - 2025-02-11

### Fixed
- Missing `Exception` import and error page styling.

[2.2.0]: https://github.com/bajoski34/Laravel/compare/2.1.1...HEAD
[2.1.1]: https://github.com/bajoski34/Laravel/compare/2.1.0...2.1.1
[2.1.0]: https://github.com/bajoski34/Laravel/compare/1.0.5...2.1.0
[1.0.5]: https://github.com/bajoski34/Laravel/compare/1.0.4...1.0.5
[1.0.4]: https://github.com/bajoski34/Laravel/compare/1.0.3...1.0.4
