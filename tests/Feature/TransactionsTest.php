<?php

use Flutterwave\Payments\Exception\NetworkConnection;
use Flutterwave\Payments\Facades\Flutterwave;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function fakeVerify(array $data): void
{
    Http::fake([FLW_API.'transactions/*' => Http::response(['status' => 'success', 'data' => $data])]);
}

it('verifies a transaction by id', function () {
    fakeVerify(['id' => 123, 'status' => 'successful']);

    expect(Flutterwave::verifyTransaction(123)['data']['status'])->toBe('successful');
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transactions/123/verify');
});

it('verifies a transaction by reference', function () {
    fakeVerify(['tx_ref' => 'ORDER 1', 'status' => 'successful']);

    Flutterwave::verifyTransactionReference('ORDER 1');

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transactions/verify_by_reference?tx_ref=ORDER%201');
});

it('confirms a payment matches the expected amount and currency', function () {
    fakeVerify(['id' => 1, 'status' => 'successful', 'amount' => 5000, 'currency' => 'NGN']);

    expect(Flutterwave::isSuccessful(1))->toBeTrue()
        ->and(Flutterwave::isSuccessful(1, 5000, 'ngn'))->toBeTrue()
        ->and(Flutterwave::isSuccessful(1, 6000, 'NGN'))->toBeFalse()
        ->and(Flutterwave::isSuccessful(1, 5000, 'USD'))->toBeFalse();
});

it('does not treat failed transactions as successful', function () {
    fakeVerify(['id' => 1, 'status' => 'failed', 'amount' => 5000, 'currency' => 'NGN']);

    expect(Flutterwave::isSuccessful(1))->toBeFalse();
});

it('returns the error body instead of throwing for legacy transaction calls', function () {
    Http::fake([FLW_API.'*' => Http::response(['status' => 'error', 'message' => 'No transaction was found for this id'], 400)]);

    expect(Flutterwave::verifyTransaction(999))->toBe(['status' => 'error', 'message' => 'No transaction was found for this id']);
});

it('refunds fully or partially', function () {
    Http::fake([FLW_API.'*' => Http::response(['status' => 'success', 'data' => []])]);

    Flutterwave::refund(55);
    Flutterwave::refund(55, 1000);

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transactions/55/refund' && $request->data() === []);
    Http::assertSent(fn (Request $request) => ($request->data()['amount'] ?? null) === 1000);
});

it('calls the correct fee endpoint', function () {
    Http::fake([FLW_API.'*' => Http::response(['status' => 'success', 'data' => ['fee' => 140]])]);

    Flutterwave::transactions()->fees(['amount' => 1000, 'currency' => 'NGN']);

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transactions/fee?amount=1000&currency=NGN');
});

it('retries GET requests on connection failures', function () {
    $calls = 0;
    Http::fake(function () use (&$calls) {
        if (++$calls === 1) {
            throw new ConnectionException('timeout');
        }

        return Http::response(['status' => 'success', 'data' => ['status' => 'successful']]);
    });

    expect(Flutterwave::verifyTransaction(1)['status'])->toBe('success')
        ->and($calls)->toBe(2);
});

it('never retries POST requests so money is not moved twice', function () {
    $calls = 0;
    Http::fake(function () use (&$calls) {
        $calls++;
        throw new ConnectionException('timeout');
    });

    try {
        Flutterwave::transfers()->create(['account_bank' => '044', 'account_number' => '0690000040', 'amount' => 500, 'currency' => 'NGN']);
    } catch (NetworkConnection) {
    }

    expect($calls)->toBe(1);
});
