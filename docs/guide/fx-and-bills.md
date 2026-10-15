# FX and bills

## Exchange rates

The rate Flutterwave uses for transfers:

```php
use Flutterwave\Payments\Facades\Flutterwave;

Flutterwave::transfers()->rates(100, destinationCurrency: 'NGN', sourceCurrency: 'USD');
```

```bash
php artisan flutterwave:rate --amount=100 --from=USD --to=NGN
```

## Trading currencies

Convert between your own wallets in two steps. A quote locks a rate you can trade within five minutes.

```php
$quote = Flutterwave::fx()->quote('NGN', 'USD', 1_500_000, reference: 'TREASURY-0042');
$quoteId = $quote['data']['id'];

// The quote starts PROCESSING. Wait for READY (or listen for QuoteCompleted), then trade:
Flutterwave::fx()->tradeIfReady($quoteId, narration: 'Supplier payment');
```

`tradeIfReady()` re-checks the quote first, so an expired one fails with a clear message instead of an API error. Use `findQuote()`, `trade()` and `findTrade()` for full control.

The `QuoteCompleted` event fires whenever a quote changes status; `$event->isReady()` tells you when to trade.

From the terminal, the quote command waits for the rate by default:

```bash
php artisan flutterwave:fx-quote NGN USD 1500000
php artisan flutterwave:fx-trade <quote-id> --narration="Supplier payment"
php artisan flutterwave:fx-status <trade-id> --trade
```

The [admin page](/guide/admin) has an `FxConverter` that does the same in the browser.

## Bill payments

Airtime, data, electricity, cable TV and more. Find the codes, validate the customer, then pay.

```php
Flutterwave::bills()->categories('NG');                 // AIRTIME, UTILITYBILLS, ...
Flutterwave::bills()->billers('UTILITYBILLS', 'NG');    // e.g. BIL119
Flutterwave::bills()->items('BIL119');                  // e.g. UB157

// Checks the meter or smartcard number and the provider's limits before paying
$payment = Flutterwave::bills()->payValidated('BIL119', 'UB157', '0123456789', 5000);

Flutterwave::bills()->status($payment['data']['tx_ref']);
```

`pay()` skips the validation step, and `history()` lists past payments.

```bash
php artisan flutterwave:bills                    # categories
php artisan flutterwave:bills UTILITYBILLS       # billers in a category
php artisan flutterwave:bills --biller=BIL119    # what a biller sells
```

## Stablecoins

Send USDT, USDC and RLUSD to wallets, and fund stablecoin balances from fiat. This needs a live, production-approved account with whitelisted IPs.

| Network | Coins |
| --- | --- |
| Ethereum | USDT, USDC, RLUSD |
| Solana | USDT, USDC |
| Polygon | USDT, USDC |
| Base | USDC |

```php
use Flutterwave\Payments\Data\Stablecoin;

Flutterwave::stablecoins()->fee(100, Stablecoin::USDC);
Flutterwave::stablecoins()->send(Stablecoin::BASE, '0x...', 100, Stablecoin::USDC);

// Pay from your NGN balance and convert in one step
Flutterwave::stablecoins()->send(Stablecoin::POLYGON, '0x...', 100, Stablecoin::USDT, debitCurrency: 'NGN');

Flutterwave::stablecoins()->fund(500, Stablecoin::USDC, fromCurrency: 'USD');
Flutterwave::stablecoins()->balances();
```

Unsupported network and coin pairs, and malformed wallet addresses, are rejected before anything is sent.
