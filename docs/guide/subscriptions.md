# Subscriptions

Flutterwave runs recurring card payments against a payment plan. Create the plan once, then pass its id at checkout.

## Create a plan

```php
use Flutterwave\Payments\Facades\Flutterwave;

$plan = Flutterwave::plans()->create(
    name: 'Pro monthly',
    amount: 5000,
    interval: 'monthly',   // daily, weekly, monthly, quarterly, every 6 months or yearly
    duration: 12,          // optional: number of charges before it stops
    currency: 'NGN',
);
```

## Subscribe a customer

```php
return Flutterwave::redirect([
    'amount' => 5000,
    'email' => $user->email,
    'payment_plan' => $plan['data']['id'],
]);
```

Every renewal is a `charge.completed` webhook, so the [`ChargeCompleted`](/guide/webhooks) listener that fulfils one-off orders can extend the subscription too.

## Manage

```php
Flutterwave::subscriptions()->all(['email' => $user->email]);
Flutterwave::subscriptions()->cancel($id);
Flutterwave::subscriptions()->activate($id);

Flutterwave::plans()->update($planId, ['name' => 'Pro (legacy)']);
Flutterwave::plans()->cancel($planId);
```

Cancellations, from you or the customer, fire `SubscriptionCancelled`.

## Want more control?

For charging on your own schedule (metered billing, trials, retries), save the card with the [`Billable` trait](/guide/saved-cards) and call `chargeSavedCard()` from a scheduled job.
