<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

function doctor(array $options = []): array
{
    $code = Artisan::call('flutterwave:doctor', $options);

    return [$code, Artisan::output()];
}

it('passes a good setup and checks the key with the api', function () {
    URL::forceRootUrl('https://shop.example.com');
    URL::forceScheme('https');
    config(['cache.default' => 'file', 'flutterwave.publicKey' => 'FLWPUBK_TEST-public']);
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => [['currency' => 'NGN'], ['currency' => 'USD']]])]);

    [$code, $output] = doctor();

    expect($code)->toBe(0)
        ->and($output)->toContain('Set (test mode)')
        ->toContain('https://shop.example.com/flutterwave/webhook')
        ->toContain('Flutterwave accepted the secret key (2 wallet balances)')
        ->toContain('Flutterwave is set up correctly.');
});

it('fails on missing keys and a missing secret hash', function () {
    config(['flutterwave.secretKey' => '', 'flutterwave.secretHash' => '']);
    Http::fake();

    [$code, $output] = doctor();

    expect($code)->toBe(1)
        ->and($output)->toContain('FLW_SECRET_KEY is empty')->toContain('every webhook is rejected')->toContain('2 problems need fixing');
    Http::assertNothingSent();
});

it('fails when test and live keys are mixed', function () {
    config(['flutterwave.publicKey' => 'FLWPUBK-live-public']);

    [$code, $output] = doctor(['--offline' => true]);

    expect($code)->toBe(1)->and($output)->toContain('One key is a test key and the other is live.');
});

it('warns about setups that work but will cause trouble', function () {
    URL::forceRootUrl('http://shop.test');
    config(['cache.default' => 'array', 'flutterwave.redirectUrl' => '', 'flutterwave.webhook.queue' => 'payments', 'queue.default' => 'sync']);
    Route::middleware('web')->group(fn () => Route::flutterwaveAdmin());
    app('router')->getRoutes()->refreshNameLookups();

    [$code, $output] = doctor(['--offline' => true]);

    expect($code)->toBe(0)
        ->and($output)->toContain("can't be reached by Flutterwave")
        ->toContain('forgets everything between requests')
        ->toContain('FLW_REDIRECT_URL is empty')
        ->toContain('connection is sync')
        ->toContain("flutterwave.manage ability isn't defined")
        ->toContain('Review the 5 warnings above.')
        ->not->toContain('API access');
});

it('reports the api rejecting the key', function () {
    config(['cache.default' => 'file']);
    Gate::define('flutterwave.manage', fn () => true);
    Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Invalid authorization key'], 401)]);

    [$code, $output] = doctor();

    expect($code)->toBe(1)->and($output)->toContain('Flutterwave rejected the request: Invalid authorization key');
});
