# Admin page and components

## The admin page

One line gives your team a page for balances, transactions, refunds, settlements, beneficiaries, chargebacks and FX:

```php
// routes/web.php
Route::middleware(['web', 'auth'])->group(function () {
    Route::flutterwaveAdmin();               // /flutterwave
    // Route::flutterwaveAdmin('billing');   // or another prefix
});
```

The page refunds customers and moves money, so it must sit inside your own authenticated group. Every page and action also checks the `flutterwave.manage` ability, which you define:

```php
// AppServiceProvider::boot()
Gate::define('flutterwave.manage', fn (User $user) => $user->is_admin);
```

To use the endpoints behind the components without the page itself, pass `false`: `Route::flutterwaveAdmin('flutterwave', false)`.

Publish the views to change the layout: `php artisan vendor:publish --tag=flutterwave-views`.

## Blade components

Every piece of the admin page is also a component you can drop into your own views. They share Flutterwave's look and need no build step.

| Component | Use |
| --- | --- |
| `<x-flutterwave-button amount="5000" email="jane@example.com" />` | A pay button that opens the inline checkout |
| `<x-flutterwave-virtual-account amount="5000" email="..." tx-ref="ORDER-42" />` | Bank details for a dynamic [virtual account](/guide/virtual-accounts), with a copy button and countdown |
| `<x-flutterwave-bank-select name="account_bank" />` | A searchable bank dropdown (cached for a day) |
| `<x-flutterwave-transactions :panel="..." />` | Filterable transactions with refund and resend-webhook actions |
| `<x-flutterwave-refunds :panel="..." />` | Refund history |
| `<x-flutterwave-settlements :panel="..." />` | Settlements and what was deducted |
| `<x-flutterwave-beneficiaries :panel="..." />` | Saved transfer recipients |
| `<x-flutterwave-chargebacks :panel="..." />` | Disputes with accept and decline forms |
| `<x-flutterwave-fx :panel="..." />` | Quote and trade currencies |

The list components take the matching service's `panel()` data, built from the request's query string so filters and paging work:

```blade
<x-flutterwave-transactions :panel="Flutterwave::transactions()->panel(request()->query())" />
<x-flutterwave-chargebacks :panel="Flutterwave::chargebacks()->panel(['status' => 'initiated'])" />
<x-flutterwave-fx :panel="Flutterwave::fx()->panel(request('fx_quote'), request('fx_trade'))" />
```

Actions post to the routes from `Route::flutterwaveAdmin()`. Without those routes, lists are read-only.

The same components exist for [Inertia apps](/guide/inertia) in React, Vue and Svelte.
