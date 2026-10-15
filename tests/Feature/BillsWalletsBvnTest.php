<?php

use Flutterwave\Payments\Exception\InvalidArgument;
use Flutterwave\Payments\Facades\Flutterwave;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

function okFake(): void
{
    Http::fake([FLW_API.'*' => Http::response(['status' => 'success', 'data' => []])]);
}

it('walks bill categories, billers and items', function () {
    okFake();

    Flutterwave::bills()->categories('ng');
    Flutterwave::bills()->billers('utilitybills');
    Flutterwave::bills()->items('BIL119');
    Flutterwave::bills()->validate('CB141', '0025401100');
    Flutterwave::bills()->status('CF-FLYAPI-2024');
    Flutterwave::bills()->history(['status' => 'Failed', 'from' => '2026-10-01']);

    foreach (['top-bill-categories?country=NG', 'bills/UTILITYBILLS/billers?country=NG', 'billers/BIL119/items', 'bill-items/CB141/validate?customer=0025401100', 'bills/CF-FLYAPI-2024?verbose=1', 'bills/history?status=Failed&from=2026-10-01'] as $path) {
        Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->url() === FLW_API.$path);
    }
});

it('pays a bill with a generated reference', function () {
    okFake();

    Flutterwave::bills()->pay('BIL119', 'CB141', '0025401100', 1900, ['callback_url' => 'https://shop.test/bills/callback']);

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'billers/BIL119/items/CB141/payment'
        && $request['country'] === 'NG' && $request['customer_id'] === '0025401100' && $request['amount'] === 1900
        && str_starts_with($request['reference'], 'LARAVEL-') && $request['callback_url'] === 'https://shop.test/bills/callback');
});

it('only pays a validated bill within the limits', function () {
    $fake = Flutterwave::fake()->respond('GET', 'bill-items/CB141/validate', ['status' => 'success', 'data' => ['response_code' => '00', 'minimum' => 500, 'maximum' => 50000]]);
    Flutterwave::bills()->payValidated('BIL119', 'CB141', '0025401100', 1900);
    $fake->assertBillPaid(fn (array $bill) => $bill['amount'] === 1900);

    expect(fn () => Flutterwave::bills()->payValidated('BIL119', 'CB141', '0025401100', 100))->toThrow(InvalidArgument::class, 'between 500 and 50000');

    Flutterwave::fake()->respond('GET', 'bill-items/CB141/validate', ['status' => 'success', 'data' => ['response_code' => '01', 'response_message' => 'Invalid smartcard number']]);
    expect(fn () => Flutterwave::bills()->payValidated('BIL119', 'CB141', '99', 1900))->toThrow(InvalidArgument::class, "couldn't confirm the customer number 99: Invalid smartcard number");
});

it('checks bill payments before calling the api', function (array $arguments, string $message) {
    Http::fake();

    expect(fn () => Flutterwave::bills()->pay(...$arguments))->toThrow(InvalidArgument::class, $message);
    Http::assertNothingSent();
})->with([
    'zero amount' => [['BIL099', 'AT099', '08038291822', 0], 'greater than zero'],
    'no customer' => [['BIL099', 'AT099', ' ', 500], 'needs the customer number'],
]);

it('manages payout subaccount wallets', function () {
    okFake();
    $wallets = Flutterwave::payoutSubaccounts();

    $wallets->create('Jane Doe', 'jane@example.com', 'ng', '08100000000');
    $wallets->all(['email' => 'jane@example.com']);
    $wallets->find('PSA789349DA8D9247156');
    $wallets->update('PSA789349DA8D9247156', ['account_name' => 'Jane D', 'email' => 'jane@example.com', 'mobilenumber' => '08100000000']);
    $wallets->balance('PSA789349DA8D9247156', 'ngn');
    $wallets->transactions('PSA789349DA8D9247156', '2026-10-01', '2026-10-15', ['page' => 2]);
    $wallets->staticAccount('PSA789349DA8D9247156', true);
    $wallets->delete('PSA789349DA8D9247156');

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'payout-subaccounts' && $request->data() === ['account_name' => 'Jane Doe', 'email' => 'jane@example.com', 'country' => 'NG', 'mobilenumber' => '08100000000']);
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'payout-subaccounts?email=jane%40example.com');
    Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request->url() === FLW_API.'payout-subaccounts/PSA789349DA8D9247156');
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'payout-subaccounts/PSA789349DA8D9247156/balances?currency=NGN');
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'payout-subaccounts/PSA789349DA8D9247156/transactions?from=2026-10-01&to=2026-10-15&page=2');
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'payout-subaccounts/PSA789349DA8D9247156/static-account?currency=NGN&verbose=1');
    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && $request->url() === FLW_API.'payout-subaccounts/PSA789349DA8D9247156');
});

it('needs a name and email for a wallet', function () {
    Http::fake();

    expect(fn () => Flutterwave::payoutSubaccounts()->create('', 'jane@example.com'))->toThrow(InvalidArgument::class, "customer's name and email");
    Http::assertNothingSent();
});

it('requests bvn consent and checks the result', function () {
    Http::fake([
        FLW_API.'bvn/verifications' => Http::response(['status' => 'success', 'data' => ['url' => 'https://nibss-bvn-consent-management.myflutterwave.com/cms/BvnConsent?session=abc', 'reference' => 'FLW0B0BCB7D3F8A1148C87232']]),
        FLW_API.'bvn/verifications/FLW0B0BCB7D3F8A1148C87232*' => Http::response(['status' => 'success', 'data' => [
            'first_name' => 'Wrong', 'last_name' => 'Names', 'status' => 'COMPLETED', 'reference' => 'FLW0B0BCB7D3F8A1148C87232',
            'bvn_data' => ['bvn' => '22222222280', 'firstName' => 'Nibb', 'surname' => 'Certifier', 'faceImage' => '/9j/4AAQ'],
        ]]),
    ]);

    $consent = Flutterwave::bvn()->requestConsent('22222222280', 'Nibb', 'Certifier', 'https://shop.test/kyc/done');

    expect($consent['data']['reference'])->toBe('FLW0B0BCB7D3F8A1148C87232')
        ->and(Flutterwave::bvn()->matches('FLW0B0BCB7D3F8A1148C87232', 'certifier', ' NIBB '))->toBeTrue()
        // The top-level names are the ones sent with the request; only the BVN record counts.
        ->and(Flutterwave::bvn()->matches('FLW0B0BCB7D3F8A1148C87232', 'Wrong', 'Names'))->toBeFalse();

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'bvn/verifications' && $request->data() === ['bvn' => '22222222280', 'firstname' => 'Nibb', 'lastname' => 'Certifier', 'redirect_url' => 'https://shop.test/kyc/done']);
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'bvn/verifications/FLW0B0BCB7D3F8A1148C87232?include_complete_message=1');
});

it('does not match a bvn before consent or without the record', function () {
    Http::fake([FLW_API.'*' => Http::sequence()
        ->push(['status' => 'success', 'data' => ['status' => 'PENDING', 'first_name' => 'Nibb', 'last_name' => 'Certifier']])
        ->push(['status' => 'success', 'data' => ['status' => 'COMPLETED', 'first_name' => 'Nibb', 'last_name' => 'Certifier']])]);

    expect(Flutterwave::bvn()->matches('FLW1', 'Nibb', 'Certifier'))->toBeFalse()
        ->and(Flutterwave::bvn()->matches('FLW1', 'Nibb', 'Certifier'))->toBeFalse();
});

it('rejects a bvn that is not 11 digits', function () {
    Http::fake();

    expect(fn () => Flutterwave::bvn()->requestConsent('1234', 'Jane', 'Doe'))->toThrow(InvalidArgument::class, 'A BVN is 11 digits.');
    Http::assertNothingSent();
});

it('lists bill categories and billers from the command line', function () {
    Http::fake([
        FLW_API.'top-bill-categories*' => Http::response(['status' => 'success', 'data' => [['code' => 'AIRTIME', 'name' => 'Airtime'], ['code' => 'UTILITYBILLS', 'name' => 'Utility Bills']]]),
        FLW_API.'bills/CABLEBILLS/billers*' => Http::response(['status' => 'success', 'data' => [['biller_code' => 'BIL119', 'name' => 'DSTV']]]),
    ]);

    $this->artisan('flutterwave:bills')->expectsOutputToContain('UTILITYBILLS')->assertSuccessful();
    $this->artisan('flutterwave:bills', ['category' => 'CABLEBILLS'])->expectsOutputToContain('BIL119')->assertSuccessful();
});

it('lists what a biller sells from the command line', function () {
    Http::fake([FLW_API.'billers/BIL119/items' => Http::response(['status' => 'success', 'data' => [['item_code' => 'CB140', 'short_name' => 'DSTV Premium', 'amount' => 14600, 'fee' => 100, 'label_name' => 'SmartCard Number']]])]);

    // The item's details share one table row, so check the whole output at once. This stays in its
    // own test: on Laravel 9 and 10, Artisan::output() is empty after $this->artisan() in the same test.
    expect(Artisan::call('flutterwave:bills', ['--biller' => 'BIL119']))->toBe(0)
        ->and(Artisan::output())->toContain('CB140')->toContain('DSTV Premium')->toContain('14,600.00')->toContain('SmartCard Number')->toContain("payValidated('BIL119'");
});
