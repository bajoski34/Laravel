<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

/**
 * Look up refunds. Create one with Flutterwave::refund($transactionId, $amount).
 */
class Refunds extends Service
{
    public function all(array $filters = []): array
    {
        return $this->client->get('refunds', $filters);
    }

    public function find(int|string $id): array
    {
        return $this->client->get("refunds/{$id}");
    }
}
