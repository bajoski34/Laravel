<?php

use Flutterwave\Payments\Events\ChargeCompleted;
use Flutterwave\Payments\Events\SubscriptionCancelled;
use Flutterwave\Payments\Events\TransferCompleted;
use Flutterwave\Payments\Events\WebhookReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function chargePayload(): array
{
    return [
        'event' => 'charge.completed',
        'data' => ['id' => 4975363, 'tx_ref' => 'ORDER-1', 'status' => 'successful', 'amount' => 5000, 'currency' => 'NGN'],
    ];
}

it('rejects webhooks with a bad or missing signature', function () {
    Event::fake();

    $this->postJson('/flutterwave/webhook', chargePayload())->assertStatus(401);
    $this->postJson('/flutterwave/webhook', chargePayload(), ['verif-hash' => 'wrong'])->assertStatus(401);

    Event::assertNotDispatched(WebhookReceived::class);
    Event::assertNotDispatched(ChargeCompleted::class);
});

it('rejects every webhook when no secret hash is configured', function () {
    config(['flutterwave.secretHash' => '']);
    app()->forgetInstance('flutterwave');

    $this->postJson('/flutterwave/webhook', chargePayload(), ['verif-hash' => ''])->assertStatus(401);
});

it('verifies the charge with the API before firing ChargeCompleted', function () {
    Event::fake();
    Http::fake(['https://api.flutterwave.com/v3/transactions/4975363/verify' => Http::response([
        'status' => 'success',
        'data' => ['id' => 4975363, 'tx_ref' => 'ORDER-1', 'status' => 'successful', 'amount' => 5000, 'currency' => 'NGN', 'customer' => ['email' => 'jane@example.com']],
    ])]);

    $this->postJson('/flutterwave/webhook', chargePayload(), ['verif-hash' => 'my-secret-hash'])
        ->assertOk()
        ->assertJson(['status' => 'success']);

    Event::assertDispatched(WebhookReceived::class, fn ($e) => $e->event === 'charge.completed');
    Event::assertDispatched(ChargeCompleted::class, function (ChargeCompleted $e) {
        return $e->verified
            && $e->isSuccessful()
            && $e->reference() === 'ORDER-1'
            && $e->amount() === 5000.0
            && $e->currency() === 'NGN'
            && $e->customerEmail() === 'jane@example.com';
    });
});

it('trusts the API over a tampered webhook body', function () {
    Event::fake();
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['id' => 4975363, 'status' => 'failed', 'amount' => 5000]])]);

    $this->postJson('/flutterwave/webhook', chargePayload(), ['verif-hash' => 'my-secret-hash'])->assertOk();

    Event::assertDispatched(ChargeCompleted::class, fn (ChargeCompleted $e) => ! $e->isSuccessful());
});

it('asks Flutterwave to retry when verification fails', function () {
    Event::fake();
    Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'down'], 503)]);

    $this->postJson('/flutterwave/webhook', chargePayload(), ['verif-hash' => 'my-secret-hash'])->assertStatus(500);

    Event::assertNotDispatched(ChargeCompleted::class);
});

it('accepts the HMAC flutterwave-signature header', function () {
    Event::fake();
    config(['flutterwave.webhook.verify_charges' => false]);
    $body = json_encode(chargePayload());
    $signature = base64_encode(hash_hmac('sha256', $body, 'my-secret-hash', true));

    $this->call('POST', '/flutterwave/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_FLUTTERWAVE_SIGNATURE' => $signature,
    ], $body)->assertOk();

    Event::assertDispatched(ChargeCompleted::class, fn (ChargeCompleted $e) => ! $e->verified && $e->isSuccessful());
    Http::assertNothingSent();
});

it('dispatches transfer and subscription events', function () {
    Event::fake();

    $this->postJson('/flutterwave/webhook', ['event' => 'transfer.completed', 'data' => ['reference' => 'PAYOUT-1', 'status' => 'SUCCESSFUL']], ['verif-hash' => 'my-secret-hash'])->assertOk();
    $this->postJson('/flutterwave/webhook', ['event' => 'subscription.cancelled', 'data' => ['id' => 1]], ['verif-hash' => 'my-secret-hash'])->assertOk();

    Event::assertDispatched(TransferCompleted::class, fn ($e) => $e->isSuccessful() && $e->reference() === 'PAYOUT-1');
    Event::assertDispatched(SubscriptionCancelled::class);
});

it('handles legacy webhooks without an event key', function () {
    Event::fake();

    $this->postJson('/flutterwave/webhook', ['event.type' => 'CARD_TRANSACTION', 'id' => 1], ['verif-hash' => 'my-secret-hash'])->assertOk();

    Event::assertDispatched(WebhookReceived::class, fn ($e) => $e->event === 'CARD_TRANSACTION' && $e->data['id'] === 1);
});

it('keeps the low level Webhooks service backwards compatible', function () {
    $webhooks = app('flutterwave')->use('webhooks');

    expect($webhooks->verifySignature('{"data":{"id":1}}', null))->toBeFalse()
        ->and($webhooks->verifySignature('{"data":{"id":1}}', 'my-secret-hash'))->toBeTrue()
        ->and($webhooks->getHook())->toBe(['id' => 1]);
});
