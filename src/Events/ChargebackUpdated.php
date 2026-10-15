<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired for chargeback webhooks: chargeback.initiated, .accepted, .declined and .lost.
 * Flutterwave only sends these once you ask them to enable chargeback webhooks.
 */
class ChargebackUpdated
{
    use Dispatchable;

    public function __construct(public string $event, public array $data) {}

    /** initiated, accepted, declined, won, lost or reversed. */
    public function status(): string
    {
        return strtolower((string) ($this->data['status'] ?? substr($this->event, strlen('chargeback.'))));
    }

    /** A new chargeback needs your response before its due date. */
    public function needsResponse(): bool
    {
        return $this->event === 'chargeback.initiated';
    }
}
