---
layout: home

hero:
  name: Flutterwave for Laravel
  text: Payments, payouts and webhooks, the Laravel way
  tagline: Take payments across Africa and beyond with a facade, events and artisan commands you already know how to use.
  image:
    src: /logo.svg
    alt: Flutterwave Laravel
  actions:
    - theme: brand
      text: Get started
      link: /guide/getting-started
    - theme: alt
      text: Try the tools
      link: /tools/
    - theme: alt
      text: GitHub
      link: https://github.com/bajoski34/Laravel

features:
  - icon: 💳
    title: Checkout in one line
    details: Flutterwave::redirect() for the hosted page, inlineConfig() for the modal, and ready-made React, Vue and Svelte buttons for Inertia apps.
    link: /guide/checkout
  - icon: 🔐
    title: Webhooks done for you
    details: A signed route, re-verification of every charge, dedupe across retries, optional queueing, and plain Laravel events to listen to.
    link: /guide/webhooks
  - icon: 💸
    title: More than collections
    details: Transfers, virtual accounts, subscriptions, saved cards, FX, bill payments, BVN checks, chargebacks and stablecoins. 21 services in total.
    link: /reference/services
  - icon: 🧰
    title: Artisan toolkit
    details: flutterwave:doctor checks your setup. Resolve accounts, list banks, verify transactions and replay webhooks from the terminal.
    link: /reference/artisan
  - icon: 🧪
    title: Testable
    details: Flutterwave::fake() answers every API call with a realistic response and gives you assertions like assertCheckoutCreated().
    link: /guide/testing
  - icon: 🖥️
    title: Admin out of the box
    details: Route::flutterwaveAdmin() adds a page for transactions, refunds, settlements, chargebacks and FX, behind your own auth.
    link: /guide/admin
---

## Install

```bash
composer require abraham-flutterwave/laravel-payment
php artisan flutterwave:install
```

Then [take your first payment](/guide/checkout):

```php
use Flutterwave\Payments\Facades\Flutterwave;

return Flutterwave::redirect([
    'amount' => 5000,
    'currency' => 'NGN',
    'email' => $user->email,
]);
```

Supports PHP 8.1+ and Laravel 9 to 13.
