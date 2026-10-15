# Inertia (React, Vue, Svelte)

The package ships typed components for Inertia apps in React 18/19, Vue 3 and Svelte 5, tested against Inertia 1, 2 and 3. They use Flutterwave's look and load the checkout script for you.

## Publish the components

Pick one framework:

```bash
php artisan flutterwave:install --react
php artisan flutterwave:install --vue
php artisan flutterwave:install --svelte
```

They're copied to `resources/js/components/flutterwave`, so they're yours to edit, and import from `@/components/flutterwave`.

## The pay button

Build the checkout config on the server with `inlineConfig()`, so the amount can't be changed in the browser, and pass it as a prop:

```php
use Flutterwave\Payments\Facades\Flutterwave;
use Inertia\Inertia;

public function show(Order $order)
{
    return Inertia::render('Checkout', [
        'checkout' => Flutterwave::inlineConfig([
            'tx_ref' => $order->reference,
            'amount' => $order->total,
            'currency' => $order->currency,
            'email' => $order->user->email,
        ]),
    ]);
}
```

::: code-group

```tsx [React]
import { router } from '@inertiajs/react';
import { FlutterwavePayButton, type FlutterwaveCheckoutConfig } from '@/components/flutterwave';

export default function Checkout({ checkout }: { checkout: FlutterwaveCheckoutConfig }) {
    return (
        <FlutterwavePayButton
            config={checkout}
            onSuccess={(payment) => router.post('/orders/confirm', { transaction_id: payment.transaction_id })}
            onClose={(incomplete) => incomplete && console.log('Customer left without paying')}
        >
            Pay now
        </FlutterwavePayButton>
    );
}
```

```vue [Vue]
<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { FlutterwavePayButton, type FlutterwaveCheckoutConfig } from '@/components/flutterwave';

defineProps<{ checkout: FlutterwaveCheckoutConfig }>();
</script>

<template>
    <FlutterwavePayButton
        :config="checkout"
        :on-success="(payment) => router.post('/orders/confirm', { transaction_id: payment.transaction_id })"
    >
        Pay now
    </FlutterwavePayButton>
</template>
```

```svelte [Svelte]
<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { FlutterwavePayButton, type FlutterwaveCheckoutConfig } from '@/components/flutterwave';

    let { checkout }: { checkout: FlutterwaveCheckoutConfig } = $props();
</script>

<FlutterwavePayButton
    config={checkout}
    onSuccess={(payment) => router.post('/orders/confirm', { transaction_id: payment.transaction_id })}
>
    Pay now
</FlutterwavePayButton>
```

:::

Passing `onSuccess` keeps the customer on your page (Flutterwave ignores callbacks when a `redirect_url` is set, so the component drops it). Leave `onSuccess` out to use the redirect instead. Either way, [verify the payment on your server](/guide/verifying-payments).

## Your own button

In React and Vue, `useFlutterwaveCheckout()` loads the script and tells you when it's ready:

```ts
import { useFlutterwaveCheckout } from '@/components/flutterwave';

const { open, ready, error } = useFlutterwaveCheckout();

open(checkout, { onSuccess, onClose });
```

In Svelte, or anywhere else, call the plain functions:

```ts
import { loadFlutterwave, openCheckout } from '@/components/flutterwave';

await loadFlutterwave();
openCheckout(checkout, { onSuccess, onClose });
```

## Hosted checkout from an Inertia form

`Flutterwave::redirect()` notices Inertia's XHR requests and returns a `409` with `X-Inertia-Location`, so `router.post('/pay')` leaves for Flutterwave's page without extra code.

## Other components

The same folder has the components behind the [admin page](/guide/admin): `Transactions`, `Refunds`, `Settlements`, `Chargebacks`, `Beneficiaries`, `FxConverter`, `VirtualAccount` and `BankSelect`. Each takes the matching `panel()` data as a prop.
