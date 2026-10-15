<?php

use Flutterwave\Payments\Exception\InvalidArgument;
use Flutterwave\Payments\Facades\Flutterwave;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;

function fakeVirtualAccount(string $accountNumber = '9519802749'): void
{
    Http::fake([FLW_API.'virtual-account-numbers' => Http::response(['status' => 'success', 'message' => 'Virtual account created', 'data' => [
        'response_code' => '02',
        'order_ref' => 'URF_1781110128750_5918935',
        'account_number' => $accountNumber,
        'bank_name' => 'Flutterwave MFB',
        'created_at' => '2026-06-10 17:48:49',
        'expiry_date' => '2026-06-10 18:48:49',
        'note' => 'Please make a bank transfer to Tester FLW',
        'amount' => '5070.00',
    ]])]);
}

it('creates a dynamic virtual account', function () {
    fakeVirtualAccount();

    Flutterwave::virtualAccounts()->dynamic('jane@example.com', 5000, ['currency' => 'ghs', 'expires' => 1800, 'narration' => 'Order 42']);

    Http::assertSent(fn (Request $request) => $request['email'] === 'jane@example.com'
        && $request['amount'] === 5000
        && $request['currency'] === 'GHS'
        && $request['expires'] === '1800'
        && $request['is_permanent'] === false
        && $request['narration'] === 'Order 42'
        && str_starts_with($request['tx_ref'], 'LARAVEL-'));
});

it('validates dynamic virtual accounts before calling the api', function (array $arguments, string $message) {
    Http::fake();

    try {
        Flutterwave::virtualAccounts()->dynamic(...$arguments);
        $this->fail('Expected an InvalidArgument exception.');
    } catch (InvalidArgument $e) {
        expect($e->getMessage())->toContain($message);
    }

    Http::assertNothingSent();
})->with([
    'zero amount' => [['jane@example.com', 0], 'greater than zero'],
    'unsupported currency' => [['jane@example.com', 10, ['currency' => 'USD']], 'NGN and GHS'],
    'expiry too short' => [['jane@example.com', 10, ['expires' => 30]], '60 to 5270401'],
    'GHS expiry too long' => [['jane@example.com', 10, ['currency' => 'GHS', 'expires' => 172801]], '60 to 172800'],
]);

it('renders the bank details with a countdown', function () {
    fakeVirtualAccount();

    $html = Blade::render('<x-flutterwave-virtual-account amount="5000" email="jane@example.com" class="checkout-box">Use your banking app.</x-flutterwave-virtual-account>');

    expect($html)
        ->toContain('9519802749')
        ->toContain('Flutterwave MFB')
        ->toContain('NGN 5,070.00')
        ->toContain('Please make a bank transfer to Tester FLW')
        ->toContain('Use your banking app.')
        ->toContain('class="flw flw-card flw-card--narrow flw-account flutterwave-virtual-account checkout-box"')
        ->toContain('data-countdown');

    preg_match('/data-expires-at="(\d+)"/', $html, $matches);
    expect((int) $matches[1] - time())->toBeGreaterThan(3590)->toBeLessThanOrEqual(3600);
});

it('reuses the account for the same tx_ref until it expires', function () {
    fakeVirtualAccount();

    $first = Blade::render('<x-flutterwave-virtual-account amount="5000" email="jane@example.com" tx-ref="ORDER-42" />');
    $second = Blade::render('<x-flutterwave-virtual-account amount="5000" email="jane@example.com" tx-ref="ORDER-42" />');

    expect($first)->toContain('9519802749')->and($second)->toContain('9519802749');
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request['tx_ref'] === 'ORDER-42');
});

it('creates a new account on every render without a tx_ref', function () {
    fakeVirtualAccount();

    Blade::render('<x-flutterwave-virtual-account amount="5000" email="jane@example.com" />');
    Blade::render('<x-flutterwave-virtual-account amount="5000" email="jane@example.com" />');

    Http::assertSentCount(2);
});

it('renders an account created elsewhere without calling the api', function () {
    Http::fake();

    $html = Blade::render('<x-flutterwave-virtual-account :account="$account" currency="GHS" />', ['account' => [
        'status' => 'success',
        'data' => ['account_number' => '9990000169539', 'bank_name' => 'First Bank Ghana', 'amount' => '2000.00', 'expiry_date' => '2026-09-14 11:32:53'],
    ]]);

    expect($html)
        ->toContain('9990000169539')
        ->toContain('GHS 2,000.00')
        ->toContain('Expires 2026-09-14 11:32:53')
        ->not->toContain('data-expires-at');
    Http::assertNothingSent();
});

it('returns a dynamic account ready for an inertia prop', function () {
    fakeVirtualAccount();

    $account = Flutterwave::virtualAccounts()->dynamicFor('jane@example.com', 5000, 'ORDER-42', ['expires' => 3600]);

    expect($account)->toMatchArray([
        'account_number' => '9519802749',
        'bank_name' => 'Flutterwave MFB',
        'amount' => '5070.00',
        'currency' => 'NGN',
        'tx_ref' => 'ORDER-42',
    ])
        ->and($account['expires_in'])->toBeGreaterThan(3590)->toBeLessThanOrEqual(3600)
        ->and(strtotime($account['expires_at']) - time())->toBe($account['expires_in']);

    Http::assertSent(fn (Request $request) => $request['tx_ref'] === 'ORDER-42' && $request['expires'] === '3600');
});

it('reuses the cached account across dynamicFor calls and the component', function () {
    fakeVirtualAccount();

    $account = Flutterwave::virtualAccounts()->dynamicFor('jane@example.com', 5000, 'ORDER-42');
    $again = Flutterwave::virtualAccounts()->dynamicFor('jane@example.com', 5000, 'ORDER-42');
    $html = Blade::render('<x-flutterwave-virtual-account amount="5000" email="jane@example.com" tx-ref="ORDER-42" />');

    expect($again['account_number'])->toBe($account['account_number'])->and($html)->toContain('9519802749');
    Http::assertSentCount(1);
});

it('shows a countdown for an account from dynamicFor passed to the component', function () {
    fakeVirtualAccount();
    $account = Flutterwave::virtualAccounts()->dynamicFor('jane@example.com', 5000, null, ['currency' => 'GHS']);

    $html = Blade::render('<x-flutterwave-virtual-account :account="$account" />', ['account' => $account]);

    expect($html)->toContain('data-expires-at="'.strtotime($account['expires_at']).'"')->toContain('GHS 5,070.00');
});
