<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired for every verified webhook Flutterwave sends.
 */
class WebhookReceived
{
    use Dispatchable;

    public function __construct(
        public string $event,
        public array $data,
        public array $payload,
    ) {}
}
