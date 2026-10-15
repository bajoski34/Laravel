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
- `Flutterwave::isSuccessful($id, $amount, $currency, $reference)` verifies a payment and confirms the amount, currency and tx_ref in one call. `ChargeCompleted::matches($amount, $currency, $reference)` does the same in a webhook listener.
- Optional checksum: with `FLW_CHECKSUM=true`, every checkout carries a `payload_hash`, so Flutterwave rejects one changed in the browser. Off by default. `Support\Checksum::make()` builds it for custom checkouts.
- `Flutterwave::refund($id, $amount = null)` for full and partial refunds.
- `<x-flutterwave-button>` Blade component for inline checkout.
- `<x-flutterwave-virtual-account>` Blade component: creates a dynamic NGN or GHS virtual account and shows the bank details with a copy button and an expiry countdown. Accounts are cached per `tx-ref`, so refreshing the page doesn't create a new one.
- `virtualAccounts()->dynamic($email, $amount, $options)` checks the amount, currency and expiry window before calling the API.
- `virtualAccounts()->dynamicFor($email, $amount, $txRef, $options)` returns a dynamic account as a flat array with `expires_at` and `expires_in`, cached per `tx_ref`. It's ready to pass as an Inertia prop.
- `Flutterwave::inlineConfig($data)` returns the inline checkout config as an array, for frontend frameworks.
- Components for Inertia apps in React, Vue 3 and Svelte 5, published with `php artisan flutterwave:install --react`, `--vue` or `--svelte`: `FlutterwavePayButton`, `VirtualAccount` (copy button, countdown, and reloads props until your webhook listener marks the order paid) and checkout helpers. They're written in TypeScript, safe to render with Inertia SSR, and type-checked in CI against Inertia 1, 2 and 3.
- `Flutterwave::redirect()` works with Inertia: for requests with an `X-Inertia` header, it returns a `409` with `X-Inertia-Location` so the browser visits the hosted checkout. Its return type is now `RedirectResponse|Response`.

**New API services**
- `transfers()`: single and bulk payouts, fees, FX rates, lookups and retries. A `reference` is generated when one isn't supplied.
- `stablecoins()`: send USDC, USDT and RLUSD on Solana, Ethereum, Base and Polygon, fund stablecoin wallets from NGN, USD, GBP, EUR or GHS, and check crypto fees and balances. The network, coin and wallet address format are validated before any request is sent, because crypto transfers can't be reversed.
- `banks()`: bank lists, branches and account-name resolution.
- `subaccounts()`: split payments for marketplaces.
- `plans()` and `subscriptions()`: recurring billing.
- `virtualAccounts()`: static and dynamic account numbers.
- `beneficiaries()`, `balances()`, `refunds()` and `settlements()`.
- `chargebacks()`: list and look up chargebacks, accept them, or decline them with proof. `declineWithProof()` uploads the evidence and disputes in one call, after checking that the file is a JPEG, PNG or PDF under 8MB.
- `fx()`: request FX quotes and trade them. `tradeIfReady()` re-checks that a quote hasn't expired before trading it.
- Artisan commands for both: `flutterwave:chargebacks`, `flutterwave:accept-chargeback`, `flutterwave:decline-chargeback`, `flutterwave:fx-quote`, `flutterwave:fx-trade` and `flutterwave:fx-status`. Commands that refund a customer or move money ask for confirmation, and need `--force` when run without a terminal.
- Admin components for both, in Blade, React, Vue and Svelte: `Chargebacks` (respond-by countdown, accept, dispute with proof) and `FxConverter` (rate, 5-minute countdown, confirm, follow the trade). `chargebacks()->panel()` and `fx()->panel()` build their data. The endpoints behind them are opt-in with `Route::flutterwaveAdmin()`, and every action needs the `flutterwave.manage` ability, so nothing is reachable until you set both up.

**Saved cards, bills, wallets and identity**
- `cards()->charge()`, `preauthorize()` and `chargeMany()` charge saved cards with the token from an earlier payment; `updateToken()` keeps a token working after an email change.
- `Billable` trait: `saveFlutterwaveCard()`, `chargeSavedCard()`, `preauthorizeSavedCard()` and `flutterwaveCard()`, with the token encrypted and hidden from JSON. `php artisan flutterwave:install --billable` publishes the migration.
- `preauth()`: capture, void and refund card holds.
- `bills()`: airtime, data, electricity and cable TV. `payValidated()` checks the customer number and the provider's limits before paying. Plus the `flutterwave:bills` command for finding codes.
- `payoutSubaccounts()`: customer wallets with their own balance and account number.
- `bvn()`: consent-based BVN verification, with `matches()` to compare names against the BVN record.

**Webhooks**
- Each webhook's events fire once, however many times Flutterwave delivers it. Handled webhooks are remembered in the cache, but only after they succeed, so a retry after a failed verification still runs. Set `FLW_WEBHOOK_QUEUE` to handle webhooks on a queue, with retries.
- Automatic `POST /flutterwave/webhook` route. Signatures are checked against `FLW_SECRET_HASH` using either the `verif-hash` header or the HMAC `flutterwave-signature` header.
- Laravel events: `WebhookReceived`, `ChargeCompleted`, `TransferCompleted`, `SubscriptionCancelled`, `QuoteCompleted` (FX quotes) and `ChargebackUpdated`.
- `charge.completed` transactions are re-fetched from the API before `ChargeCompleted` fires, so a forged payload can't mark an order as paid. If verification fails, the route responds with HTTP 500 so Flutterwave retries.
- `Webhooks::isValid()` checks either signature format.

**Developer experience**
- Artisan commands: `flutterwave:install` (publishes the config, adds keys to `.env` and prints the webhook URL), `flutterwave:verify` and `flutterwave:banks`.
- `Flutterwave::fake()` for testing apps that use the package: realistic responses for every endpoint, assertions such as `assertCheckoutCreated()` and `assertTransferSent()`, and signed webhook headers.
- `flutterwave:doctor` checks keys, webhooks, the cache, the queue and API access in one go.
- Sandbox helpers: `test_outcome` and `test_resolve_after` on transfers end test-mode transfers the way you choose (ignored with live keys), and `Testing\SandboxData` holds Flutterwave's official test cards, accounts, bills and BVN identities.
- Admin screen and command actions that refund customers or move money are written to an audit log, and the admin page is sent with `Cache-Control: no-store`.
- `ChargebackRaised` and `TransferFailed` notifications, sent automatically to the addresses in `FLW_NOTIFY_CHARGEBACKS` and `FLW_NOTIFY_FAILED_TRANSFERS`.
- An optional admin page from `Route::flutterwaveAdmin()`, with balances, chargebacks and the FX converter.
- Artisan commands matching the standalone Flutterwave CLI: `flutterwave:balance`, `flutterwave:branches`, `flutterwave:resolve`, `flutterwave:bin`, `flutterwave:rate`, `flutterwave:pay`, `flutterwave:resend-webhook` and `flutterwave:ping-webhook` (posts a signed webhook for a real transaction to a local URL).
- `cards()->bin($bin)` service for card BIN lookups.
- `banks()->resolveAccount()` takes an optional country for Ghanaian bank accounts and mobile money numbers, and the new `banks()->resolveMerchant($id)` checks Flutterwave merchant IDs. `flutterwave:resolve` supports both with `--country` and `--merchant`.
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
