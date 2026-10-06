<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    require __DIR__.'/../../src/routes/web.php';
    app('router')->getRoutes()->refreshNameLookups();
});

it('sends cancelled payments to the cancel page', function () {
    $this->get('/flutterwave/payment/callback?status=cancelled&tx_ref=ORDER-1')
        ->assertRedirect(route('flutterwave.cancelled'));

    Http::assertNothingSent();
});

it('verifies the transaction id on callback instead of trusting the query string', function () {
    Http::fake(['*/transactions/42/verify' => Http::response(['status' => 'success', 'data' => ['status' => 'failed']])]);

    $this->get('/flutterwave/payment/callback?status=successful&tx_ref=ORDER-1&transaction_id=42')
        ->assertRedirect(route('flutterwave.failed'));
});

it('redirects successful payments', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['status' => 'successful']])]);

    $this->get('/flutterwave/payment/callback?status=successful&tx_ref=ORDER-1')
        ->assertRedirect(route('flutterwave.successful'));
});

it('renders the inline checkout page', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class)
        ->post('/flutterwave/payment/checkout', ['amount' => 100, 'currency' => 'NGN', 'email' => 'jane@example.com'])
        ->assertOk()
        ->assertSee('FlutterwaveCheckout(flw_detail)', false)
        ->assertSee('"public_key":"FLWPUBK_TEST-public"', false);
});
