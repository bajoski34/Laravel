<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

/**
 * Saved transfer recipients.
 */
class Beneficiaries extends Service
{
    public function create(string $accountNumber, string $bankCode, ?string $name = null, ?string $currency = null): array
    {
        return $this->client->post('beneficiaries', $this->filter([
            'account_number' => $accountNumber,
            'account_bank' => $bankCode,
            'beneficiary_name' => $name,
            'currency' => $currency,
        ]));
    }

    public function all(array $filters = []): array
    {
        return $this->client->get('beneficiaries', $filters);
    }

    public function find(int|string $id): array
    {
        return $this->client->get("beneficiaries/{$id}");
    }

    public function delete(int|string $id): array
    {
        return $this->client->delete("beneficiaries/{$id}");
    }
}
