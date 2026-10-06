<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

/**
 * Send money to bank accounts and mobile money wallets.
 *
 * @see https://developer.flutterwave.com/reference/create-a-transfer
 */
class Transfers extends Service
{
    /**
     * Initiate a transfer. Required keys: account_bank, account_number, amount, currency.
     * A unique `reference` is generated when not provided so retries can be de-duplicated.
     */
    public function create(array $data): array
    {
        $data['reference'] ??= Transactions::generateTransactionReference((string) ($this->config['prefix'] ?? 'LARAVEL-'));

        return $this->client->post('transfers', $data);
    }

    /**
     * Send many transfers at once. Each item follows the same shape as create().
     */
    public function bulk(array $transfers, ?string $title = null): array
    {
        return $this->client->post('bulk-transfers', $this->filter([
            'title' => $title,
            'bulk_data' => $transfers,
        ]));
    }

    public function find(int|string $id): array
    {
        return $this->client->get("transfers/{$id}");
    }

    public function all(array $filters = []): array
    {
        return $this->client->get('transfers', $filters);
    }

    public function fee(int|float $amount, string $currency = 'NGN', ?string $type = null): array
    {
        return $this->client->get('transfers/fee', $this->filter([
            'amount' => $amount,
            'currency' => $currency,
            'type' => $type,
        ]));
    }

    /**
     * Get the exchange rate for a cross-currency transfer.
     */
    public function rates(int|float $amount, string $destinationCurrency, string $sourceCurrency): array
    {
        return $this->client->get('transfers/rates', [
            'amount' => $amount,
            'destination_currency' => $destinationCurrency,
            'source_currency' => $sourceCurrency,
        ]);
    }

    public function retry(int|string $id): array
    {
        return $this->client->post("transfers/{$id}/retries");
    }

    public function retries(int|string $id): array
    {
        return $this->client->get("transfers/{$id}/retries");
    }
}
