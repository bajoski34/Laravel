<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Exception\FlutterwaveException;

/**
 * Payouts of collected funds to your settlement account.
 *
 * @see https://developer.flutterwave.com/reference/get-all-settlements
 */
class Settlements extends Service
{
    /**
     * Filters: from, to (YYYY-MM-DD), page, subaccount_id, disburse_ref.
     */
    public function all(array $filters = []): array
    {
        return $this->client->get('settlements', $filters);
    }

    /**
     * One settlement, with the transactions it paid out (`data.transactions`).
     */
    public function find(int|string $id, ?int $page = null): array
    {
        return $this->client->get("settlements/{$id}", $this->filter(['page' => $page]));
    }

    /**
     * Everything the settlements components need. Pass the request's query: from, to and page
     * filter the list; `settlement` adds that settlement's details and transactions.
     */
    public function panel(array $query = []): array
    {
        $filters = array_filter(array_intersect_key($query, array_flip(['from', 'to', 'page'])), static fn ($value) => $value !== null && $value !== '');
        $panel = ['items' => [], 'page' => null, 'filters' => $filters, 'error' => null, 'selected' => null];

        try {
            $response = $this->all($filters);
            $panel['items'] = array_map(fn (array $settlement) => $this->present($settlement), $response['data'] ?? []);
            $panel['page'] = $response['meta']['page_info'] ?? null;

            if (! empty($query['settlement'])) {
                $settlement = $this->find((string) $query['settlement'])['data'] ?? [];
                $panel['selected'] = [
                    ...$this->present($settlement),
                    'transactions' => array_map(static fn (array $transaction) => array_intersect_key($transaction, array_flip(['id', 'tx_ref', 'customer_email', 'charged_amount', 'app_fee', 'settlement_amount', 'status', 'payment_entity', 'transaction_date', 'currency'])), $settlement['transactions'] ?? []),
                ];
            }
        } catch (FlutterwaveException $e) {
            $panel['error'] = $e->getMessage();
        }

        return $panel;
    }

    private function present(array $settlement): array
    {
        return array_intersect_key($settlement, array_flip([
            'id', 'status', 'currency', 'gross_amount', 'app_fee', 'merchant_fee', 'chargeback', 'refund', 'stampduty_charge', 'net_amount',
            'transaction_count', 'transaction_date', 'due_date', 'processed_date', 'settlement_account', 'bank_code', 'destination', 'flag_message', 'disburse_ref',
        ]));
    }
}
