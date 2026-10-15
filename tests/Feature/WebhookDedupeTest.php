<?php

use Flutterwave\Payments\Events\ChargebackUpdated;
use Flutterwave\Payments\Events\ChargeCompleted;
use Flutterwave\Payments\Events\TransferCompleted;
use Flutterwave\Payments\Jobs\HandleFlutterwaveWebhook;
use Flutterwave\Payments\Webhooks\WebhookProcessor;
use Flutterwave\Payments\Webhooks\WebhookVerificationFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function postWebhook(array $payload)
{
    return test()->postJson('/flutterwave/webhook', $payload, ['verif-hash' => 'my-secret-hash']);
}

function chargeWebhook(string $status = 'successful'): array
{
    return ['event' => 'charge.completed', 'data' => ['id' => 4975363, 'tx_ref' => 'ORDER-1', 'status' => $status, 'amount' => 5000, 'currency' => 'NGN']];
}

it('fires the events for a webhook only once', function () {
    Event::fake([ChargeCompleted::class]);
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => chargeWebhook()['data']])]);

    postWebhook(chargeWebhook())->assertOk()->assertJson(['status' => 'success']);
    postWebhook(chargeWebhook())->assertOk()->assertJson(['message' => 'Already handled.']);

    Event::assertDispatchedTimes(ChargeCompleted::class, 1);
    Http::assertSentCount(1);
});

it('still handles the retry after a failed verification', function () {
    Event::fake([ChargeCompleted::class]);
    Http::fake(['*' => Http::sequence()
        ->push(['status' => 'error', 'message' => 'Service unavailable'], 503)
        ->push(['status' => 'success', 'data' => chargeWebhook()['data']])]);

    postWebhook(chargeWebhook())->assertStatus(500);
    Event::assertNotDispatched(ChargeCompleted::class);

    postWebhook(chargeWebhook())->assertOk()->assertJson(['status' => 'success']);
    Event::assertDispatchedTimes(ChargeCompleted::class, 1);
});

it('treats a new status for the same thing as a new webhook', function () {
    Event::fake([ChargebackUpdated::class, TransferCompleted::class]);

    postWebhook(['event' => 'chargeback.initiated', 'data' => ['id' => 1599, 'status' => 'initiated']])->assertOk();
    postWebhook(['event' => 'chargeback.lost', 'data' => ['id' => 1599, 'status' => 'lost']])->assertOk();
    postWebhook(['event' => 'transfer.completed', 'data' => ['id' => 77, 'status' => 'FAILED']])->assertOk();
    postWebhook(['event' => 'transfer.completed', 'data' => ['id' => 77, 'status' => 'FAILED']])->assertOk();

    Event::assertDispatchedTimes(ChargebackUpdated::class, 2);
    Event::assertDispatchedTimes(TransferCompleted::class, 1);
});

it('asks flutterwave to retry while the same webhook is being handled', function () {
    Event::fake([TransferCompleted::class]);
    $payload = ['event' => 'transfer.completed', 'data' => ['id' => 77, 'status' => 'SUCCESSFUL']];
    $lock = Cache::lock(WebhookProcessor::key($payload).':lock', 30);
    $lock->get();

    postWebhook($payload)->assertStatus(409);
    Event::assertNotDispatched(TransferCompleted::class);

    $lock->release();
    postWebhook($payload)->assertOk();
    Event::assertDispatchedTimes(TransferCompleted::class, 1);
});

it('can be turned off', function () {
    config(['flutterwave.webhook.dedupe' => false]);
    Event::fake([TransferCompleted::class]);
    $payload = ['event' => 'transfer.completed', 'data' => ['id' => 77, 'status' => 'SUCCESSFUL']];

    postWebhook($payload)->assertOk();
    postWebhook($payload)->assertOk();

    Event::assertDispatchedTimes(TransferCompleted::class, 2);
});

it('queues webhooks when a queue is configured', function () {
    config(['flutterwave.webhook.queue' => 'payments', 'flutterwave.webhook.queue_connection' => 'redis']);
    Queue::fake();

    postWebhook(chargeWebhook())->assertOk()->assertJson(['message' => 'Queued.']);

    Queue::assertPushedOn('payments', HandleFlutterwaveWebhook::class, fn (HandleFlutterwaveWebhook $job) => $job->connection === 'redis' && $job->payload['data']['id'] === 4975363);
});

it('lets the queued job retry when a charge cannot be verified yet', function () {
    Event::fake([ChargeCompleted::class]);
    Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Service unavailable'], 503)]);

    expect(fn () => (new HandleFlutterwaveWebhook(chargeWebhook()))->handle(app(WebhookProcessor::class)))->toThrow(WebhookVerificationFailed::class);
    expect((new HandleFlutterwaveWebhook([]))->backoff())->toBe([60, 300, 900, 3600, 10800]);
    Event::assertNotDispatched(ChargeCompleted::class);
});

it('handles a queued webhook once', function () {
    Event::fake([TransferCompleted::class]);
    $payload = ['event' => 'transfer.completed', 'data' => ['id' => 77, 'status' => 'SUCCESSFUL']];

    (new HandleFlutterwaveWebhook($payload))->handle(app(WebhookProcessor::class));
    (new HandleFlutterwaveWebhook($payload))->handle(app(WebhookProcessor::class));

    Event::assertDispatchedTimes(TransferCompleted::class, 1);
});
