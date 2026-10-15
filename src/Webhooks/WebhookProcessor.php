<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Webhooks;

use Flutterwave\Payments\Events\ChargebackUpdated;
use Flutterwave\Payments\Events\ChargeCompleted;
use Flutterwave\Payments\Events\QuoteCompleted;
use Flutterwave\Payments\Events\SubscriptionCancelled;
use Flutterwave\Payments\Events\TransferCompleted;
use Flutterwave\Payments\Events\WebhookReceived;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Turns a verified webhook payload into Laravel events, at most once per webhook.
 *
 * Flutterwave retries webhooks until it gets a 2xx, and this package asks it to
 * when a charge can't be verified, so the same webhook can arrive more than once.
 * Each one is remembered in the cache only after it has been handled, so a failed
 * attempt is retried, and a lock stops two copies arriving together from both running.
 */
final class WebhookProcessor
{
    public const HANDLED = 'handled';

    public const DUPLICATE = 'duplicate';

    public const BUSY = 'busy';

    /**
     * @return string one of HANDLED, DUPLICATE or BUSY (another copy is being handled right now)
     *
     * @throws WebhookVerificationFailed when a charge can't be confirmed with the API
     */
    public function handle(array $payload): string
    {
        if (! config('flutterwave.webhook.dedupe', true)) {
            $this->dispatch($payload);

            return self::HANDLED;
        }

        $key = self::key($payload);
        $cache = $this->cache();
        $lock = $cache->getStore() instanceof LockProvider ? $cache->getStore()->lock($key.':lock', 30) : null;

        if ($lock && ! $lock->get()) {
            return self::BUSY;
        }

        try {
            if ($cache->has($key)) {
                Log::channel('flutterwave')->info('Flutterwave Webhook::Skipped a duplicate '.self::event($payload));

                return self::DUPLICATE;
            }

            $this->dispatch($payload);
            $cache->put($key, time(), (int) config('flutterwave.webhook.dedupe_for', 7 * 24 * 3600));

            return self::HANDLED;
        } finally {
            $lock?->release();
        }
    }

    /**
     * What makes a webhook the same webhook: its event, the id of what it's about and that
     * thing's status, so e.g. chargeback.initiated and chargeback.lost for one chargeback both run.
     */
    public static function key(array $payload): string
    {
        $data = self::data($payload);
        $identity = isset($data['id'])
            ? self::event($payload).'|'.$data['id'].'|'.strtolower((string) ($data['status'] ?? ''))
            : sha1((string) json_encode($payload));

        return 'flutterwave:webhook:'.sha1($identity);
    }

    public static function event(array $payload): string
    {
        return (string) ($payload['event'] ?? $payload['event.type'] ?? 'unknown');
    }

    private static function data(array $payload): array
    {
        return is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
    }

    private function dispatch(array $payload): void
    {
        $event = self::event($payload);
        $data = self::data($payload);

        Log::channel('flutterwave')->info("Flutterwave Webhook::Received {$event}");

        if ($event === 'charge.completed') {
            [$transaction, $verified] = $this->verifiedCharge($data);

            WebhookReceived::dispatch($event, $data, $payload);
            ChargeCompleted::dispatch($data, $transaction, $verified);

            return;
        }

        WebhookReceived::dispatch($event, $data, $payload);

        match (true) {
            $event === 'transfer.completed' => TransferCompleted::dispatch($data),
            $event === 'subscription.cancelled' => SubscriptionCancelled::dispatch($data),
            $event === 'quote.completed' => QuoteCompleted::dispatch($data),
            str_starts_with($event, 'chargeback.') => ChargebackUpdated::dispatch($event, $data),
            default => null,
        };
    }

    /**
     * @return array{0: array, 1: bool}
     */
    private function verifiedCharge(array $data): array
    {
        if (! config('flutterwave.webhook.verify_charges', true) || ! isset($data['id'])) {
            return [$data, false];
        }

        $response = app('flutterwave')->verifyTransaction($data['id']);

        if (($response['status'] ?? null) !== 'success' || ! isset($response['data'])) {
            Log::channel('flutterwave')->error("Flutterwave Webhook::Could not verify transaction {$data['id']}", $response);

            throw new WebhookVerificationFailed("Transaction {$data['id']} could not be verified.");
        }

        return [$response['data'], true];
    }

    private function cache(): Repository
    {
        return Cache::store(config('flutterwave.webhook.dedupe_store'));
    }
}
