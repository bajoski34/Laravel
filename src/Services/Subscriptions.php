<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

/**
 * Manage customers subscribed to payment plans.
 *
 * @see https://developer.flutterwave.com/docs/recurring-payments/subscriptions
 */
class Subscriptions extends Service
{
    /**
     * Filter by email, plan, status, transaction_id, from, to, page...
     */
    public function all(array $filters = []): array
    {
        return $this->client->get('subscriptions', $filters);
    }

    public function activate(int|string $id): array
    {
        return $this->client->put("subscriptions/{$id}/activate");
    }

    public function cancel(int|string $id): array
    {
        return $this->client->put("subscriptions/{$id}/cancel");
    }
}
