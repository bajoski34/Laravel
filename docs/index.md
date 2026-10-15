---
layout: home
markdownStyles: false
pageClass: landing-page
title: Flutterwave for Laravel
titleTemplate: false
---

<HomeLanding>
<template #checkout>

```php
public function store(Order $order)
{
    return Flutterwave::redirect([
        'tx_ref' => $order->reference,
        'amount' => $order->total,
        'currency' => 'NGN',
        'email' => $order->customer_email,
    ]);
}
```

</template>
<template #webhook>

```php
public function handle(ChargeCompleted $event): void
{
    // Signature checked, duplicates dropped, and the
    // charge re-fetched from the API before this runs.
    if ($event->isSuccessful()) {
        Order::firstWhere('reference', $event->reference())
            ?->markPaid();
    }
}
```

</template>
<template #payout>

```php
public function handle(): void
{
    Flutterwave::transfers()->create([
        'account_bank' => $this->seller->bank_code,
        'account_number' => $this->seller->account_number,
        'amount' => $this->sale->net_amount,
        'currency' => 'NGN',
        'narration' => "Payout for sale #{$this->sale->id}",
    ]);
}
```

</template>
<template #test>

```php
it('sends the customer to checkout', function () {
    $flutterwave = Flutterwave::fake();

    $this->post("/orders/{$order->id}/pay")->assertRedirect();

    $flutterwave->assertCheckoutCreated(
        fn (array $payment) => $payment['amount'] === $order->total
    );
});
```

</template>
</HomeLanding>
