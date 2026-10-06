<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

/**
 * Dedicated bank account numbers your customers can pay into.
 *
 * @see https://developer.flutterwave.com/docs/collecting-payments/virtual-account-numbers
 */
class VirtualAccounts extends Service
{
    /**
     * Required keys: email. Pass is_permanent=true with a bvn for a static account.
     */
    public function create(array $data): array
    {
        $data['tx_ref'] ??= Transactions::generateTransactionReference((string) ($this->config['prefix'] ?? 'LARAVEL-'));

        return $this->client->post('virtual-account-numbers', $data);
    }

    public function bulk(array $data): array
    {
        return $this->client->post('bulk-virtual-account-numbers', $data);
    }

    public function find(string $orderRef): array
    {
        return $this->client->get("virtual-account-numbers/{$orderRef}");
    }

    public function findBulk(string $batchId): array
    {
        return $this->client->get("bulk-virtual-account-numbers/{$batchId}");
    }

    public function updateBvn(string $orderRef, string $bvn): array
    {
        return $this->client->put("virtual-account-numbers/{$orderRef}", ['bvn' => $bvn]);
    }

    public function delete(string $orderRef): array
    {
        return $this->client->post("virtual-account-numbers/{$orderRef}", ['status' => 'inactive']);
    }
}
