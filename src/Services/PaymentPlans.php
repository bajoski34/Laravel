<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

/**
 * Recurring billing plans. Pass the plan id as `payment_plan` when
 * starting a checkout to subscribe the customer.
 *
 * @see https://developer.flutterwave.com/docs/recurring-payments/payment-plans
 */
class PaymentPlans extends Service
{
    /**
     * @param  string  $interval  daily, weekly, monthly, quarterly, every 6 months or yearly
     */
    public function create(string $name, int|float $amount, string $interval, ?int $duration = null, ?string $currency = null): array
    {
        return $this->client->post('payment-plans', $this->filter([
            'name' => $name,
            'amount' => $amount,
            'interval' => $interval,
            'duration' => $duration,
            'currency' => $currency ?? ($this->config['currency'] ?? null),
        ]));
    }

    public function all(array $filters = []): array
    {
        return $this->client->get('payment-plans', $filters);
    }

    public function find(int|string $id): array
    {
        return $this->client->get("payment-plans/{$id}");
    }

    /**
     * Update the name and/or status ("active") of a plan.
     */
    public function update(int|string $id, array $data): array
    {
        return $this->client->put("payment-plans/{$id}", $data);
    }

    public function cancel(int|string $id): array
    {
        return $this->client->put("payment-plans/{$id}/cancel");
    }
}
