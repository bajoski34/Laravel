<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('resolves an account name', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['account_number' => '0690000032', 'account_name' => 'Pastor Bright']])]);

    $this->artisan('flutterwave:resolve', ['account' => '0690000032', 'bank' => '044'])
        ->expectsOutputToContain('Pastor Bright')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'accounts/resolve' && $request['account_bank'] === '044');
});

it('resolves a ghanaian mobile money number with a country', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['account_number' => '233542773934', 'account_name' => 'Kwame Mensah']])]);

    $this->artisan('flutterwave:resolve', ['account' => '233542773934', 'bank' => 'MTN', '--country' => 'gh'])
        ->expectsOutputToContain('Kwame Mensah')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->data() === ['account_number' => '233542773934', 'account_bank' => 'MTN', 'country' => 'GH']);
});

it('resolves a flutterwave merchant id', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['account_number' => '7981223', 'account_name' => 'Flutterwave Developer Account']])]);

    $this->artisan('flutterwave:resolve', ['account' => '7981223', '--merchant' => true])
        ->expectsOutputToContain('Flutterwave Developer Account')
        ->expectsOutputToContain('merchant id')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->data() === ['account_number' => '7981223', 'account_bank' => 'flutterwave']);
});

it('needs a bank code unless resolving a merchant id', function () {
    Http::fake();

    $this->artisan('flutterwave:resolve', ['account' => '0690000032'])
        ->expectsOutputToContain('--merchant')
        ->assertFailed();

    Http::assertNothingSent();
});

it('shows the api error and a sandbox hint when an account cannot be resolved', function () {
    Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Sorry, that account number is invalid, please check and try again', 'data' => null], 400)]);

    $this->artisan('flutterwave:resolve', ['account' => '0123456789', 'bank' => '058'])
        ->expectsOutputToContain('that account number is invalid')
        ->expectsOutputToContain('Test keys can only resolve Flutterwave test accounts')
        ->assertFailed();
});

it('shows balances, optionally for one currency', function () {
    Http::fake([
        FLW_API.'balances/USD' => Http::response(['status' => 'success', 'data' => ['currency' => 'USD', 'available_balance' => 42, 'ledger_balance' => 50]]),
        FLW_API.'balances' => Http::response(['status' => 'success', 'data' => [
            ['currency' => 'NGN', 'available_balance' => 1000, 'ledger_balance' => 1200],
            ['currency' => 'GHS', 'available_balance' => 7, 'ledger_balance' => 7],
        ]]),
    ]);

    $this->artisan('flutterwave:balance')
        ->expectsOutputToContain('GHS')
        ->assertSuccessful();

    $this->artisan('flutterwave:balance', ['--currency' => 'usd'])
        ->expectsOutputToContain('42')
        ->doesntExpectOutputToContain('NGN')
        ->assertSuccessful();
});

it('lists bank branches', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => [
        ['branch_code' => 'GH190101', 'branch_name' => 'Accra Main', 'swift_code' => 'ACCRGHAC'],
        ['branch_code' => 'GH190102', 'branch_name' => 'Kumasi', 'swift_code' => 'ACCRGHAC'],
    ]])]);

    $this->artisan('flutterwave:branches', ['bank' => '280', '--search' => 'accra'])
        ->expectsOutputToContain('Accra Main')
        ->doesntExpectOutputToContain('Kumasi')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'banks/280/branches');
});

it('looks up a card bin', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => [
        'issuing_country' => 'NIGERIA NG', 'bin' => '539983', 'card_type' => 'MASTERCARD', 'issuer_info' => 'MASTERCARD GTBANK',
    ]])]);

    $this->artisan('flutterwave:bin', ['bin' => '5399 8312 3456 7890'])
        ->expectsOutputToContain('MASTERCARD GTBANK')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'card-bins/539983');
});

it('shows the transfer exchange rate', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => [
        'rate' => 1500, 'source' => ['currency' => 'USD', 'amount' => 1], 'destination' => ['currency' => 'NGN', 'amount' => 1500],
    ]])]);

    $this->artisan('flutterwave:rate', ['--amount' => '1500', '--from' => 'usd', '--to' => 'ngn'])
        ->expectsOutputToContain('1500')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transfers/rates?amount=1500&destination_currency=NGN&source_currency=USD');
});

it('creates a payment link', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/v3/hosted/pay/abc']])]);

    $this->artisan('flutterwave:pay', ['email' => 'jane@example.com', 'amount' => '100', '--currency' => 'usd'])
        ->expectsOutputToContain('https://checkout.flutterwave.com/v3/hosted/pay/abc')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'payments'
        && $request['currency'] === 'USD'
        && $request['customer']['email'] === 'jane@example.com');
});

it('resends a failed webhook', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'message' => 'Webhook queued for resend'])]);

    $this->artisan('flutterwave:resend-webhook', ['transaction' => '4975363'])
        ->expectsOutputToContain('Webhook queued for resend')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transactions/4975363/resend-hook');
});

it('pings a local webhook url with a signed payload', function () {
    Http::fake([
        FLW_API.'*' => Http::response(['status' => 'success', 'data' => ['id' => 7, 'tx_ref' => 'ORDER-7', 'status' => 'successful']]),
        'http://localhost:8000/*' => Http::response(['status' => 'ok']),
    ]);

    $this->artisan('flutterwave:ping-webhook', ['id' => '7', 'url' => 'http://localhost:8000/flutterwave/webhook'])
        ->expectsOutputToContain('charge.completed sent to http://localhost:8000/flutterwave/webhook')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === 'http://localhost:8000/flutterwave/webhook'
        && $request['event'] === 'charge.completed'
        && $request['data']['tx_ref'] === 'ORDER-7'
        && $request->header('verif-hash')[0] === 'my-secret-hash'
        && $request->header('flutterwave-signature')[0] === base64_encode(hash_hmac('sha256', $request->body(), 'my-secret-hash', true)));
});

it('pings a transfer webhook', function () {
    Http::fake([
        FLW_API.'transfers/9' => Http::response(['status' => 'success', 'data' => ['id' => 9, 'status' => 'SUCCESSFUL']]),
        'http://localhost:8000/*' => Http::response(['status' => 'ok']),
    ]);

    $this->artisan('flutterwave:ping-webhook', ['id' => '9', 'url' => 'http://localhost:8000/hook', '--event' => 'transfer'])
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === 'http://localhost:8000/hook' && $request['event'] === 'transfer.completed');
});

it('rejects unknown webhook events', function () {
    $this->artisan('flutterwave:ping-webhook', ['id' => '9', '--event' => 'refund'])
        ->expectsOutputToContain('charge or transfer')
        ->assertFailed();
});
