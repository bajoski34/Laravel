<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Exception\FlutterwaveException;

/**
 * Look up refunds. Create one with Flutterwave::refund($transactionId, $amount).
 *
 * @see https://developer.flutterwave.com/reference/get-all-refunds
 */
class Refunds extends Service
{
    /**
     * Filters: from, to (YYYY-MM-DD), status (successful, failed, completed), currency, flw_ref,
     * id (the transaction id), page.
     */
    public function all(array $filters = []): array
    {
        return $this->client->get('refunds', $filters);
    }

    /**
     * Fetch one refund by its id (data.id in the refund response).
     */
    public function find(int|string $id): array
    {
        return $this->client->get("refunds/{$id}");
    }

    /**
     * Everything the refunds components need. Pass the request's query: from, to, status,
     * currency and page filter the list.
     */
    public function panel(array $query = []): array
    {
        $filters = array_filter(array_intersect_key($query, array_flip(['from', 'to', 'status', 'currency', 'page'])), static fn ($value) => $value !== null && $value !== '');

        try {
            $response = $this->all($filters);
        } catch (FlutterwaveException $e) {
            return ['items' => [], 'page' => null, 'filters' => $filters, 'error' => $e->getMessage()];
        }

        return [
            'items' => array_map(static fn (array $refund) => [
                'id' => $refund['id'] ?? null,
                'transaction_id' => $refund['transaction_id'] ?? $refund['tx_id'] ?? null,
                'amount_refunded' => $refund['amount_refunded'] ?? null,
                'status' => $refund['status'] ?? null,
                'flw_ref' => $refund['flw_ref'] ?? null,
                'comment' => $refund['comment'] ?? null,
                'settlement_id' => $refund['settlement_id'] ?? null,
                'created_at' => $refund['created_at'] ?? null,
            ], $response['data'] ?? []),
            'page' => $response['meta']['page_info'] ?? null,
            'filters' => $filters,
            'error' => null,
        ];
    }
}
