<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired for `transfer.completed` webhooks (successful or failed payouts).
 */
class TransferCompleted
{
    use Dispatchable;

    public function __construct(public array $data) {}

    public function isSuccessful(): bool
    {
        return strtoupper((string) ($this->data['status'] ?? '')) === 'SUCCESSFUL';
    }

    public function reference(): ?string
    {
        return $this->data['reference'] ?? null;
    }
}
