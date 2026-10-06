<?php

use Flutterwave\Payments\Data\Api;
use Flutterwave\Payments\Exception\FlutterwaveException;
use Flutterwave\Payments\Exception\ServiceNotFound;
use Flutterwave\Payments\Facades\Flutterwave;
use Flutterwave\Payments\Services\Banks;
use Flutterwave\Payments\Services\Transfers;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([FLW_API.'*' => Http::response(['status' => 'success', 'message' => 'ok', 'data' => []])]);
});

function assertCalled(string $method, string $path, ?array $body = null): void
{
    Http::assertSent(function (Request $request) use ($method, $path, $body) {
        return $request->method() === $method
            && $request->url() === FLW_API.$path
            && ($body === null || $request->data() === $body);
    });
}

it('sends transfers with an auto-generated reference', function () {
    Flutterwave::transfers()->create(['account_bank' => '044', 'account_number' => '0690000040', 'amount' => 500, 'currency' => 'NGN']);

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transfers'
        && str_starts_with($request['reference'], 'LARAVEL-'));
});

it('covers the transfers API', function () {
    $transfers = Flutterwave::transfers();
    $transfers->bulk([['amount' => 1]], 'Payroll');
    $transfers->find(7);
    $transfers->all(['status' => 'failed']);
    $transfers->fee(5000, 'NGN');
    $transfers->rates(100, 'NGN', 'USD');
    $transfers->retry(7);
    $transfers->retries(7);

    assertCalled('POST', 'bulk-transfers', ['title' => 'Payroll', 'bulk_data' => [['amount' => 1]]]);
    assertCalled('GET', 'transfers/7');
    assertCalled('GET', 'transfers?status=failed');
    assertCalled('GET', 'transfers/fee?amount=5000&currency=NGN');
    assertCalled('GET', 'transfers/rates?amount=100&destination_currency=NGN&source_currency=USD');
    assertCalled('POST', 'transfers/7/retries');
    assertCalled('GET', 'transfers/7/retries');
});

it('covers banks and account resolution', function () {
    Flutterwave::banks()->all('gh');
    Flutterwave::banks()->branches(280);
    Flutterwave::banks()->resolveAccount('0690000032', '044');

    assertCalled('GET', 'banks/GH');
    assertCalled('GET', 'banks/280/branches');
    assertCalled('POST', 'accounts/resolve', ['account_number' => '0690000032', 'account_bank' => '044']);
});

it('covers subaccounts', function () {
    $subaccounts = Flutterwave::subaccounts();
    $subaccounts->create(['business_name' => 'Vendor']);
    $subaccounts->all();
    $subaccounts->find('RS_1');
    $subaccounts->update('RS_1', ['split_value' => 0.2]);
    $subaccounts->delete('RS_1');

    assertCalled('POST', 'subaccounts', ['business_name' => 'Vendor']);
    assertCalled('GET', 'subaccounts');
    assertCalled('GET', 'subaccounts/RS_1');
    assertCalled('PUT', 'subaccounts/RS_1', ['split_value' => 0.2]);
    assertCalled('DELETE', 'subaccounts/RS_1');
});

it('covers payment plans and subscriptions', function () {
    Flutterwave::plans()->create('Gold', 5000, 'monthly', 12);
    Flutterwave::plans()->all();
    Flutterwave::plans()->find(3);
    Flutterwave::plans()->update(3, ['name' => 'Platinum']);
    Flutterwave::plans()->cancel(3);
    Flutterwave::subscriptions()->all(['email' => 'jane@example.com']);
    Flutterwave::subscriptions()->cancel(9);
    Flutterwave::subscriptions()->activate(9);

    assertCalled('POST', 'payment-plans', ['name' => 'Gold', 'amount' => 5000, 'interval' => 'monthly', 'duration' => 12, 'currency' => 'NGN']);
    assertCalled('GET', 'payment-plans');
    assertCalled('GET', 'payment-plans/3');
    assertCalled('PUT', 'payment-plans/3', ['name' => 'Platinum']);
    assertCalled('PUT', 'payment-plans/3/cancel');
    assertCalled('GET', 'subscriptions?email=jane%40example.com');
    assertCalled('PUT', 'subscriptions/9/cancel');
    assertCalled('PUT', 'subscriptions/9/activate');
});

it('covers virtual accounts', function () {
    $accounts = Flutterwave::virtualAccounts();
    $accounts->create(['email' => 'jane@example.com', 'is_permanent' => true, 'bvn' => '12345678901']);
    $accounts->find('URF_1');
    $accounts->findBulk('BATCH_1');
    $accounts->updateBvn('URF_1', '22222222222');
    $accounts->delete('URF_1');

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'virtual-account-numbers' && isset($request['tx_ref']));
    assertCalled('GET', 'virtual-account-numbers/URF_1');
    assertCalled('GET', 'bulk-virtual-account-numbers/BATCH_1');
    assertCalled('PUT', 'virtual-account-numbers/URF_1', ['bvn' => '22222222222']);
    assertCalled('POST', 'virtual-account-numbers/URF_1', ['status' => 'inactive']);
});

it('covers beneficiaries, balances, refunds and settlements', function () {
    Flutterwave::beneficiaries()->create('0690000032', '044', 'Jane Doe');
    Flutterwave::beneficiaries()->delete(4);
    Flutterwave::balances()->all();
    Flutterwave::balances()->currency('ngn');
    Flutterwave::refunds()->all();
    Flutterwave::refunds()->find(8);
    Flutterwave::settlements()->all(['page' => 2]);
    Flutterwave::settlements()->find(5);

    assertCalled('POST', 'beneficiaries', ['account_number' => '0690000032', 'account_bank' => '044', 'beneficiary_name' => 'Jane Doe']);
    assertCalled('DELETE', 'beneficiaries/4');
    assertCalled('GET', 'balances');
    assertCalled('GET', 'balances/NGN');
    assertCalled('GET', 'refunds');
    assertCalled('GET', 'refunds/8');
    assertCalled('GET', 'settlements?page=2');
    assertCalled('GET', 'settlements/5');
});

it('throws FlutterwaveException when a service call fails', function () {
    // Http::fake stacks, so reset the default stub registered in beforeEach.
    Http::swap(new Factory);
    Http::fake([FLW_API.'*' => Http::response(['status' => 'error', 'message' => 'Insufficient balance'], 400)]);

    Flutterwave::transfers()->create(['amount' => 1]);
})->throws(FlutterwaveException::class, 'Insufficient balance');

it('lets apps swap in their own service class', function () {
    $custom = new class(new Api, []) extends Transfers {};
    config(['flutterwave.services' => ['transfers' => get_class($custom)]]);
    app()->forgetInstance('flutterwave');
    Flutterwave::clearResolvedInstances();

    expect(Flutterwave::transfers())->toBeInstanceOf(get_class($custom))
        ->and(Flutterwave::banks())->toBeInstanceOf(Banks::class);
});

it('lets apps disable a service', function () {
    config(['flutterwave.services' => ['transfers' => null]]);
    app()->forgetInstance('flutterwave');
    Flutterwave::clearResolvedInstances();

    Flutterwave::transfers();
})->throws(ServiceNotFound::class, 'transfers service not found');
