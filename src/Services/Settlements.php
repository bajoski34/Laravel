<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

/**
 * Payouts of collected funds to your settlement account.
 */
class Settlements extends Service
{
    public function all(array $filters = []): array
    {
        return $this->client->get('settlements', $filters);
    }

    public function find(int|string $id): array
    {
        return $this->client->get("settlements/{$id}");
    }
}
