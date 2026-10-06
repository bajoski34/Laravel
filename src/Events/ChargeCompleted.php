<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Events;

use Flutterwave\Payments\Data\Status;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired for `charge.completed` webhooks.
 *
 * When `flutterwave.webhook.verify_charges` is enabled (the default), $transaction
 * holds the transaction as re-fetched from the Flutterwave API rather than the
 * webhook body, so it can be trusted for fulfilling orders.
 */
class ChargeCompleted
{
    use Dispatchable;

    public function __construct(
        public array $data,
        public array $transaction,
        public bool $verified,
    ) {}

    public function isSuccessful(): bool
    {
        return ($this->transaction['status'] ?? null) === Status::SUCCESSFUL;
    }

    public function reference(): ?string
    {
        return $this->transaction['tx_ref'] ?? null;
    }

    public function amount(): float
    {
        return (float) ($this->transaction['amount'] ?? 0);
    }

    public function currency(): ?string
    {
        return $this->transaction['currency'] ?? null;
    }

    public function customerEmail(): ?string
    {
        return $this->transaction['customer']['email'] ?? null;
    }
}
