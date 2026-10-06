<?php

use Flutterwave\Payments\Data\Stablecoin;
use Flutterwave\Payments\Exception\InvalidArgument;
use Flutterwave\Payments\Facades\Flutterwave;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const EVM_ADDRESS = '0xd0c7a1b2c3d4e5f60718293a4b5c6d7e8f901234';
const SOLANA_ADDRESS = '7EcDhSYGxXyscszYEp35KHN8vvw3svAuLKTzXwCFLtV';

beforeEach(function () {
    Http::fake([FLW_API.'*' => Http::response(['status' => 'success', 'message' => 'Transfer Queued Successfully', 'data' => ['id' => 254858, 'status' => 'NEW']])]);
});

it('sends stablecoins to a wallet on a supported network', function () {
    Flutterwave::stablecoins()->send('polygon', EVM_ADDRESS, 10, 'usdc', null, ['narration' => 'Payout']);

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transfers'
        && $request['account_bank'] === 'POLYGON'
        && $request['account_number'] === EVM_ADDRESS
        && $request['currency'] === 'USDC'
        && $request['debit_currency'] === 'USDC'
        && $request['amount'] === 10
        && $request['narration'] === 'Payout'
        && str_starts_with($request['reference'], 'LARAVEL-'));
});

it('converts from a fiat balance when sending', function () {
    Flutterwave::stablecoins()->send(Stablecoin::SOLANA, SOLANA_ADDRESS, 50, Stablecoin::USDT, 'NGN');

    Http::assertSent(fn (Request $request) => $request['account_bank'] === 'SOLANA' && $request['debit_currency'] === 'NGN');
});

it('refuses coins a network does not support', function () {
    Flutterwave::stablecoins()->send(Stablecoin::BASE, EVM_ADDRESS, 10, Stablecoin::USDT);
})->throws(InvalidArgument::class, 'USDT cannot be sent on BASE');

it('refuses unsupported networks like Tron', function () {
    Flutterwave::stablecoins()->send('TRON', 'TXYZ', 10, 'USDT');
})->throws(InvalidArgument::class, 'cannot be sent on TRON');

it('refuses an address from the wrong chain', function () {
    Flutterwave::stablecoins()->send(Stablecoin::SOLANA, EVM_ADDRESS, 10, Stablecoin::USDC);
})->throws(InvalidArgument::class, 'not a valid SOLANA wallet address');

it('refuses unsupported fiat debit currencies', function () {
    Flutterwave::stablecoins()->send(Stablecoin::ETHEREUM, EVM_ADDRESS, 10, Stablecoin::RLUSD, 'KES');
})->throws(InvalidArgument::class, 'not KES');

it('never calls the API when validation fails', function () {
    try {
        Flutterwave::stablecoins()->send(Stablecoin::BASE, 'not-an-address', 10, Stablecoin::USDC);
    } catch (InvalidArgument) {
    }

    Http::assertNothingSent();
});

it('funds a stablecoin wallet from fiat using the merchant id', function () {
    config(['flutterwave.merchantId' => '10024361']);
    app()->forgetInstance('flutterwave');
    Flutterwave::clearResolvedInstances();

    Flutterwave::stablecoins()->fund(100, 'usdc', 'ngn');

    Http::assertSent(fn (Request $request) => $request->data() === [
        'account_bank' => 'flutterwave',
        'account_number' => '10024361',
        'amount' => 100,
        'currency' => 'USDC',
        'debit_currency' => 'NGN',
        'reference' => $request['reference'],
    ]);
});

it('explains when the merchant id is missing', function () {
    Flutterwave::stablecoins()->fund(100, 'USDC', 'NGN');
})->throws(InvalidArgument::class, 'FLW_MERCHANT_ID');

it('gets crypto transfer fees', function () {
    Flutterwave::stablecoins()->fee(50, 'usdt');
    Flutterwave::stablecoins()->fee(50, 'usdt', 'ngn');

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transfers/fee?amount=50&currency=USDT&type=crypto');
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transfers/fee?amount=50&currency=USDT&type=crypto&debit_currency=NGN');
});

it('returns only stablecoin balances', function () {
    Http::swap(new Factory);
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => [
        ['currency' => 'USDC', 'available_balance' => 12, 'ledger_balance' => 12],
        ['currency' => 'NGN', 'available_balance' => 37.53, 'ledger_balance' => 0],
        ['currency' => 'USDT', 'available_balance' => 0, 'ledger_balance' => 0],
    ]])]);

    expect(array_keys(Flutterwave::stablecoins()->balances()))->toBe(['USDC', 'USDT'])
        ->and(Flutterwave::stablecoins()->balances()['USDC']['available_balance'])->toBe(12);
});

it('fetches a stablecoin transfer', function () {
    Flutterwave::stablecoins()->find(2189051);

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transfers/2189051');
});
