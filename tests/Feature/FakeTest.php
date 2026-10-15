<?php

use Flutterwave\Payments\Events\ChargeCompleted;
use Flutterwave\Payments\Exception\FlutterwaveException;
use Flutterwave\Payments\Facades\Flutterwave;
use Flutterwave\Payments\Testing\FlutterwaveFake;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;

it('creates checkouts and records them', function () {
    $fake = Flutterwave::fake();

    $link = Flutterwave::checkout(['amount' => 5000, 'email' => 'jane@example.com', 'tx_ref' => 'ORDER-42']);

    expect($link)->toStartWith('https://checkout.flutterwave.com/v3/hosted/pay/fake-');
    $fake->assertCheckoutCreated(fn (array $payment) => $payment['amount'] === 5000 && $payment['customer']['email'] === 'jane@example.com');
});

it('verifies only the transactions a test registers', function () {
    Flutterwave::fake()->transaction(4975363, ['amount' => 5000, 'currency' => 'USD', 'tx_ref' => 'ORDER-42']);

    expect(Flutterwave::isSuccessful(4975363, 5000, 'USD'))->toBeTrue()
        ->and(Flutterwave::isSuccessful(4975363, 6000))->toBeFalse()
        ->and(Flutterwave::verifyTransactionReference('ORDER-42')['data']['id'])->toBe(4975363)
        ->and(Flutterwave::verifyTransaction(1)['message'])->toContain('Register it in your test with Flutterwave::fake()->transaction(1');
});

it('fakes failures', function () {
    Flutterwave::fake()->failing('POST', 'transfers', 'Insufficient wallet balance');

    expect(fn () => Flutterwave::transfers()->create(['account_bank' => '044', 'account_number' => '0690000040', 'amount' => 500, 'currency' => 'NGN']))
        ->toThrow(FlutterwaveException::class, 'Insufficient wallet balance');
});

it('lets a test answer an endpoint itself', function () {
    Flutterwave::fake()->respond('GET', 'transfers/*', fn ($request) => ['status' => 'success', 'data' => ['id' => 7, 'status' => 'FAILED', 'complete_message' => 'Account resolve failed']]);

    expect(Flutterwave::transfers()->find(7)['data']['status'])->toBe('FAILED');
});

it('asserts transfers, refunds and saved card charges', function () {
    $fake = Flutterwave::fake();

    Flutterwave::transfers()->create(['account_bank' => '044', 'account_number' => '0690000040', 'amount' => 500, 'currency' => 'NGN']);
    Flutterwave::refund(4975363, 1000);

    $fake->assertTransferSent(fn (array $transfer) => $transfer['amount'] === 500)
        ->assertRefunded(4975363, 1000)
        ->assertNotSent('POST', 'tokenized-charges');

    expect(fn () => $fake->assertRefunded(4975363, 2000))->toThrow(AssertionFailedError::class, 'was not refunded for 2000');
    expect(fn () => $fake->assertCardCharged())->toThrow(AssertionFailedError::class, 'No saved card was charged.');
});

it('gives realistic defaults for the rest of the api', function () {
    Flutterwave::fake();

    expect(Flutterwave::banks()->resolveAccount('0690000032', '044')['data']['account_name'])->toBe('Fake Account Name')
        ->and(Flutterwave::virtualAccounts()->dynamicFor('jane@example.com', 5000, 'ORDER-9')['expires_in'])->toBeGreaterThan(3590)
        ->and(Flutterwave::fx()->findQuote('q1')['data']['status'])->toBe('READY')
        ->and(Flutterwave::balances()->all()['data'][0]['currency'])->toBe('NGN');
});

it('leaves other http fakes alone', function () {
    Http::fake(['api.example.com/*' => Http::response(['ok' => true])]);
    $fake = Flutterwave::fake();

    expect(Http::get('https://api.example.com/ping')->json('ok'))->toBeTrue();
    $fake->assertNothingSent();
});

it('signs webhooks for feature tests', function () {
    Flutterwave::fake()->transaction(4975363, ['amount' => 5000]);
    Event::fake([ChargeCompleted::class]);

    $this->postJson('/flutterwave/webhook', ['event' => 'charge.completed', 'data' => ['id' => 4975363, 'status' => 'successful']], FlutterwaveFake::webhookHeaders())->assertOk();

    Event::assertDispatched(ChargeCompleted::class, fn (ChargeCompleted $event) => $event->verified && $event->amount() === 5000.0);
});

it('works without keys in the test environment', function () {
    config(['flutterwave.secretKey' => null, 'flutterwave.publicKey' => null]);
    app()->forgetInstance('flutterwave');
    Flutterwave::clearResolvedInstances();

    $fake = Flutterwave::fake();
    Flutterwave::checkout(['amount' => 100, 'email' => 'a@b.co']);

    $fake->assertCheckoutCreated();
});

it('starts over when a test calls fake() again', function () {
    Flutterwave::fake()->failing('POST', 'transfers', 'Insufficient wallet balance');
    $fake = Flutterwave::fake();

    Flutterwave::transfers()->create(['account_bank' => '044', 'account_number' => '0690000040', 'amount' => 500, 'currency' => 'NGN']);

    $fake->assertTransferSent();
});
