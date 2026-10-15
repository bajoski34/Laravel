<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Jobs;

use Flutterwave\Payments\Webhooks\WebhookProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Handles a webhook on the queue when `flutterwave.webhook.queue` is set, so the
 * webhook route can answer Flutterwave straight away. Since Flutterwave has already
 * had its 200, this job does the retrying when a charge can't be verified yet.
 */
class HandleFlutterwaveWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 6;

    public function __construct(public array $payload) {}

    /** Seconds to wait before each retry: 1 minute, then 5, 15, 1 hour and 3 hours. */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 10800];
    }

    public function handle(WebhookProcessor $processor): void
    {
        if ($processor->handle($this->payload) === WebhookProcessor::BUSY) {
            // Another copy of this webhook is being handled; check again shortly.
            $this->release(30);
        }
    }
}
