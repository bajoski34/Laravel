# Virtual accounts

Give customers a bank account number to pay into by transfer. Payments into it arrive as normal `charge.completed` webhooks, so [`ChargeCompleted`](/guide/webhooks) handles them.

## One payment (dynamic)

A single-use NGN or GHS account for an exact amount, which expires (Flutterwave's default is one hour):

```php
use Flutterwave\Payments\Facades\Flutterwave;

$account = Flutterwave::virtualAccounts()->dynamicFor(
    email: $order->user->email,
    amount: $order->total,
    txRef: $order->reference,
    options: ['expires' => 1800],   // seconds
);
```

`dynamic($email, $amount, $options)` does the same without a reference of your own. The amount must be above zero, and only NGN and GHS are supported.

Show it with the `VirtualAccount` component (Blade, React, Vue or Svelte), which displays the bank, account number, amount and a countdown.

## A permanent account

Static accounts need the customer's BVN in Nigeria:

```php
Flutterwave::virtualAccounts()->create([
    'email' => $user->email,
    'is_permanent' => true,
    'bvn' => $bvn,
    'tx_ref' => "VA-{$user->id}",
    'firstname' => $user->first_name,
    'lastname' => $user->last_name,
    'narration' => $user->name,
]);
```

## Manage

```php
Flutterwave::virtualAccounts()->find($orderRef);
Flutterwave::virtualAccounts()->updateBvn($orderRef, $bvn);
Flutterwave::virtualAccounts()->delete($orderRef);

Flutterwave::virtualAccounts()->bulk([...]);
Flutterwave::virtualAccounts()->findBulk($batchId);
```

## BVN consent

```php
$consent = Flutterwave::bvn()->requestConsent($bvn, 'Ada', 'Lovelace', route('bvn.callback'));
// redirect the customer to approve, then:
Flutterwave::bvn()->matches($reference, 'Ada', 'Lovelace');   // bool
```

## Payout subaccounts

Hold funds for your own users, each with a static account:

```php
$sub = Flutterwave::payoutSubaccounts()->create('Ada Stores', 'ada@example.com', 'NG');

Flutterwave::payoutSubaccounts()->staticAccount($sub['data']['account_reference']);
Flutterwave::payoutSubaccounts()->balance($sub['data']['account_reference']);
```
