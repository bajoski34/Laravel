<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

/**
 * Split payments between your account and vendor subaccounts.
 *
 * @see https://developer.flutterwave.com/docs/split-payment
 */
class Subaccounts extends Service
{
    /**
     * Required keys: account_bank, account_number, business_name, business_mobile, country, split_value.
     */
    public function create(array $data): array
    {
        return $this->client->post('subaccounts', $data);
    }

    public function all(array $filters = []): array
    {
        return $this->client->get('subaccounts', $filters);
    }

    public function find(int|string $id): array
    {
        return $this->client->get("subaccounts/{$id}");
    }

    public function update(int|string $id, array $data): array
    {
        return $this->client->put("subaccounts/{$id}", $data);
    }

    public function delete(int|string $id): array
    {
        return $this->client->delete("subaccounts/{$id}");
    }
}
