<?php

use Flutterwave\Payments\Exception\FlutterwaveException;
use Flutterwave\Payments\Exception\InvalidArgument;
use Flutterwave\Payments\Facades\Flutterwave;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('creates a hosted payment link from just an amount and email', function () {
    Http::fake([FLW_API.'payments' => Http::response([
        'status' => 'success',
        'data' => ['link' => 'https://checkout.flutterwave.com/v3/hosted/pay/abc'],
    ])]);

    $link = Flutterwave::checkout(['amount' => 5000, 'email' => 'jane@example.com']);

    expect($link)->toBe('https://checkout.flutterwave.com/v3/hosted/pay/abc');

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer FLWSECK_TEST-secret')
            && $request['customer'] === ['email' => 'jane@example.com']
            && $request['currency'] === 'NGN'
            && str_starts_with($request['tx_ref'], 'LARAVEL-')
            && $request['redirect_url'] === 'https://shop.test/flutterwave/payment/callback'
            && ! isset($request['email']);
    });
});

it('redirects to the hosted checkout', function () {
    Http::fake([FLW_API.'payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.test/pay']])]);

    $response = Flutterwave::redirect(['amount' => 100, 'email' => 'jane@example.com']);

    expect($response->getTargetUrl())->toBe('https://checkout.test/pay');
});

it('keeps the legacy render() API working', function () {
    Http::fake([FLW_API.'payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.test/pay']])]);

    $link = Flutterwave::render('standard', [
        'tx_ref' => 'ORDER-1',
        'amount' => 100,
        'currency' => 'USD',
        'customer' => ['email' => 'jane@example.com'],
    ]);

    expect($link)->toBe('https://checkout.test/pay');
    Http::assertSent(fn (Request $request) => $request['tx_ref'] === 'ORDER-1' && $request['currency'] === 'USD');
});

it('builds an inline checkout config that is safe to embed in a script tag', function () {
    $json = Flutterwave::render('inline', [
        'amount' => 100,
        'email' => 'jane@example.com',
        'meta' => ['note' => "</script><script>alert('x')</script>"],
    ]);

    expect($json)->not->toContain('</script>')
        ->and(json_decode($json, true))->toMatchArray([
            'public_key' => 'FLWPUBK_TEST-public',
            'amount' => 100,
            'customer' => ['email' => 'jane@example.com'],
        ]);
});

it('throws a helpful error when the email is missing', function () {
    Flutterwave::checkout(['amount' => 100]);
})->throws(InvalidArgument::class, 'customer email is required');

it('throws a helpful error when the amount is invalid', function () {
    Flutterwave::checkout(['amount' => 0, 'email' => 'jane@example.com']);
})->throws(InvalidArgument::class, 'positive "amount"');

it('surfaces the Flutterwave error message when checkout fails', function () {
    Http::fake([FLW_API.'payments' => Http::response(['status' => 'error', 'message' => 'Invalid currency provided'], 400)]);

    try {
        Flutterwave::checkout(['amount' => 100, 'email' => 'jane@example.com']);
        $this->fail('Expected exception');
    } catch (FlutterwaveException $e) {
        expect($e->getMessage())->toBe('Invalid currency provided')
            ->and($e->getStatusCode())->toBe(400)
            ->and($e->getResponse()['status'])->toBe('error');
    }
});

it('explains when the secret key is missing', function () {
    config(['flutterwave.secretKey' => null]);
    app()->forgetInstance('flutterwave');
    Flutterwave::clearResolvedInstances();

    Flutterwave::checkout(['amount' => 100, 'email' => 'jane@example.com']);
})->throws(InvalidArgument::class, 'FLW_SECRET_KEY');
