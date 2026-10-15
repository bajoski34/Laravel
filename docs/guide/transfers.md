# Transfers

Send money to bank accounts and mobile money wallets.

## Find the bank and check the account

```php
use Flutterwave\Payments\Facades\Flutterwave;

Flutterwave::banks()->options('NG');
// [['code' => '044', 'name' => 'Access Bank'], ...] sorted by name, ready for a <select>

$account = Flutterwave::banks()->resolveAccount('0690000031', '044');
$account['data']['account_name'];
```

From the terminal: `php artisan flutterwave:banks NG --search=access` and `php artisan flutterwave:resolve 0690000031 044`.

## Send a transfer

```php
$transfer = Flutterwave::transfers()->create([
    'account_bank' => '044',
    'account_number' => '0690000031',
    'amount' => 5500,
    'currency' => 'NGN',
    'narration' => 'Payout for order #1042',
    'reference' => $payout->reference,   // generated with your prefix if left out
]);
```

The transfer starts as `NEW` or `PENDING`. Its final status arrives in the [`TransferCompleted`](/reference/events#transfercompleted) webhook event:

```php
use Flutterwave\Payments\Events\TransferCompleted;

Event::listen(function (TransferCompleted $event) {
    Payout::where('reference', $event->reference())->update([
        'status' => $event->isSuccessful() ? 'paid' : 'failed',
    ]);
});
```

Set `FLW_NOTIFY_FAILED_TRANSFERS` to get an email whenever one fails.

## Test outcomes in the sandbox

With test keys, choose how a transfer ends instead of waiting on the sandbox:

```php
Flutterwave::transfers()->create([
    // ...
    'test_outcome' => 'failure',     // or 'success'
    'test_resolve_after' => 2,       // minutes, default 10
]);
```

Both keys are dropped with live keys.

::: tip Whitelist your IP
Flutterwave only accepts transfer requests from IP addresses you've whitelisted on the dashboard, in test mode too.
:::

## Bulk, fees and rates

```php
Flutterwave::transfers()->bulk([$transferA, $transferB], title: 'October payouts');

Flutterwave::transfers()->fee(5500, 'NGN');
Flutterwave::transfers()->rates(100, destinationCurrency: 'NGN', sourceCurrency: 'USD');
```

## Retry and look up

```php
Flutterwave::transfers()->find($id);
Flutterwave::transfers()->all(['status' => 'failed']);
Flutterwave::transfers()->retry($id);
Flutterwave::transfers()->retries($id);
```

## Saved recipients

```php
Flutterwave::beneficiaries()->create('0690000031', '044', 'Ada Lovelace');
Flutterwave::beneficiaries()->all();
Flutterwave::beneficiaries()->delete($id);
```

## Balances

```php
Flutterwave::balances()->all();
Flutterwave::balances()->currency('NGN');
```

Or `php artisan flutterwave:balance`.
