<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired for `subscription.cancelled` webhooks.
 */
class SubscriptionCancelled
{
    use Dispatchable;

    public function __construct(public array $data) {}
}
