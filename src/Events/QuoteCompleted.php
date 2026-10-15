<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Events;

use Flutterwave\Payments\Services\Fx;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired for `quote.completed` webhooks, whenever an FX quote changes status.
 * Despite the name, the quote may be PROCESSING, READY, EXPIRED or FAILED.
 */
class QuoteCompleted
{
    use Dispatchable;

    public function __construct(public array $data) {}

    public function isReady(): bool
    {
        return ($this->data['status'] ?? null) === Fx::QUOTE_READY;
    }

    public function quoteId(): ?string
    {
        return $this->data['id'] ?? null;
    }
}
