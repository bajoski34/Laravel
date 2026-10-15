<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Exception\InvalidArgument;

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
     *
     * With test keys, sandbox transfers stay PENDING unless told how to end. Pass
     * test_outcome ('success' or 'failure') and optionally test_resolve_after (minutes,
     * default 10). Both are ignored with live keys.
     */
    public function create(array $data): array
    {
        return $this->client->post('transfers', $this->prepare($data));
    }

    /**
     * Send many transfers at once. Each item follows the same shape as create().
     */
    public function bulk(array $transfers, ?string $title = null): array
    {
        return $this->client->post('bulk-transfers', $this->filter([
            'title' => $title,
            'bulk_data' => array_map(fn (array $transfer) => $this->prepare($transfer, generateReference: false), $transfers),
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

    private function prepare(array $data, bool $generateReference = true): array
    {
        $outcome = $data['test_outcome'] ?? null;
        $after = $data['test_resolve_after'] ?? null;
        unset($data['test_outcome'], $data['test_resolve_after']);

        if ($generateReference || $outcome !== null) {
            $data['reference'] ??= Transactions::generateTransactionReference((string) ($this->config['prefix'] ?? 'LARAVEL-'));
        }

        if ($outcome === null || ! str_contains((string) ($this->config['secret_key'] ?? ''), '_TEST')) {
            return $data;
        }

        if (! in_array($outcome, ['success', 'failure'], true)) {
            throw new InvalidArgument("test_outcome must be 'success' or 'failure', not '{$outcome}'.");
        }

        // Flutterwave's sandbox ends a transfer by its reference suffix: _PMCK succeeds,
        // _PMCK_ST_F fails, and DU_{minutes} changes the 10-minute delay.
        $data['reference'] .= '_PMCK'.($outcome === 'failure' ? '_ST_F' : '').($after !== null ? 'DU_'.max(1, (int) $after) : '');

        return $data;
    }
}
