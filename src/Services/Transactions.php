<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Data\Api;
use Flutterwave\Payments\Exception\InvalidArgument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Psr\Log\LoggerInterface;

/**
 * Verify, list and refund collections.
 *
 * For backwards compatibility these methods return the Flutterwave error
 * body (`status: error`) instead of throwing when the API rejects a call.
 */
class Transactions extends Service
{
    private LoggerInterface $logger;

    private string $name;

    public function __construct(Api $api, array $config)
    {
        if (empty($config['secret_key'])) {
            throw new InvalidArgument('The secret key is required. please add it to the .env file.');
        }

        parent::__construct($api, $config);
        $this->name = self::class;
        $this->logger = Log::channel('flutterwave');
    }

    /**
     * Verify a transaction with transactionId
     */
    public function verify(int|string $transactionId): array
    {
        $this->logger->info("{$this->name}::Verifying transaction with id: ".$transactionId);

        return $this->client->get("transactions/{$transactionId}/verify", [], false);
    }

    /**
     * Verify a transaction with tx_ref
     */
    public function verifyTransactionReference(string $tx_ref): array
    {
        $this->logger->info("{$this->name}::Verifying transaction with reference: ".$tx_ref);

        return $this->client->get('transactions/verify_by_reference', ['tx_ref' => $tx_ref], false);
    }

    /**
     * Refund a transaction in full, or partially when an amount is given.
     * Options: comments (shown on the refund), callbackurl (Flutterwave posts the result there).
     */
    public function refund(int|string $transactionId, int|float|string|null $amount = null, array $options = []): array
    {
        $this->logger->info("{$this->name}::Refunding transaction with id: ".$transactionId);

        return $this->client->post("transactions/{$transactionId}/refund", $this->filter([
            'amount' => $amount,
            'comments' => $options['comments'] ?? null,
            'callbackurl' => $options['callbackurl'] ?? null,
        ]), false);
    }

    /**
     * Get the transactions on your account. Filters: from and to (YYYY-MM-DD, default the last
     * 30 days, as Flutterwave requires both), page, status, customer_email, customer_fullname,
     * tx_ref, currency.
     */
    public function all(?array $data = null): array
    {
        $data ??= [];
        $data['to'] ??= date('Y-m-d');
        $data['from'] ??= date('Y-m-d', strtotime($data['to'].' -30 days'));

        return $this->client->get('transactions', $data, false);
    }

    /**
     * Get transaction fee by supplying amount and currency
     */
    public function fees(array $data): array
    {
        return $this->client->get('transactions/fee', $data, false);
    }

    /**
     * What Flutterwave charges on a payment of $amount. $paymentType: card, debit_ng_account,
     * mobilemoney, bank_transfer or ach_payment. Pass the card's first six digits to include
     * international card fees.
     */
    public function fee(int|float|string $amount, string $currency, ?string $paymentType = null, ?string $cardFirst6 = null): array
    {
        return $this->fees($this->filter([
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'payment_type' => $paymentType,
            'card_first6digits' => $cardFirst6,
        ]));
    }

    /**
     * Ask Flutterwave to send a transaction's webhook again. With $wait, it waits for your
     * webhook route to answer and returns that answer.
     */
    public function resendFailedHooks(int|string $transactionId, int|bool $wait = false): array
    {
        return $this->client->post("transactions/{$transactionId}/resend-hook".($wait ? '?wait=1' : ''), [], false);
    }

    /**
     * Everything the transactions components need. Pass the request's query: from, to, page,
     * status, customer_email, tx_ref and currency filter the list; `transaction` adds that
     * transaction's timeline; fee_amount and fee_currency add a fee estimate.
     */
    public function panel(array $query = []): array
    {
        $filters = array_filter(array_intersect_key($query, array_flip(['from', 'to', 'page', 'status', 'customer_email', 'tx_ref', 'currency'])), static fn ($value) => $value !== null && $value !== '');
        $response = $this->all($filters);
        $ok = ($response['status'] ?? null) === 'success';
        $routes = Route::has('flutterwave.transactions.refund');

        $panel = [
            'items' => $ok ? array_map(fn (array $transaction) => $this->present($transaction, $routes), $response['data'] ?? []) : [],
            'page' => $response['meta']['page_info'] ?? null,
            'filters' => ['from' => $filters['from'] ?? date('Y-m-d', strtotime('-30 days')), 'to' => $filters['to'] ?? date('Y-m-d'), ...$filters],
            'error' => $ok ? null : ($response['message'] ?? 'Transactions could not be loaded.'),
            'timeline' => null,
            'fee' => null,
        ];

        if (! empty($query['transaction'])) {
            $events = $this->timeline((string) $query['transaction']);
            $panel['timeline'] = [
                'transaction_id' => (string) $query['transaction'],
                'events' => ($events['status'] ?? null) === 'success' ? array_map(static fn (array $event) => array_intersect_key($event, array_flip(['note', 'actor', 'object', 'action', 'context', 'created_at'])), $events['data'] ?? []) : [],
                'error' => ($events['status'] ?? null) === 'success' ? null : ($events['message'] ?? 'The timeline could not be loaded.'),
            ];
        }

        if (! empty($query['fee_amount']) && ! empty($query['fee_currency'])) {
            $fee = $this->fee((string) $query['fee_amount'], (string) $query['fee_currency'], $query['fee_type'] ?? null);
            $panel['fee'] = ($fee['status'] ?? null) === 'success'
                ? [...$fee['data'], 'payment_type' => $query['fee_type'] ?? null, 'error' => null]
                : ['error' => $fee['message'] ?? 'The fee could not be worked out.'];
        }

        return $panel;
    }

    private function present(array $transaction, bool $routes): array
    {
        $status = strtolower((string) ($transaction['status'] ?? ''));

        return [
            'id' => $transaction['id'] ?? null,
            'tx_ref' => $transaction['tx_ref'] ?? null,
            'flw_ref' => $transaction['flw_ref'] ?? null,
            'amount' => $transaction['amount'] ?? null,
            'charged_amount' => $transaction['charged_amount'] ?? null,
            'app_fee' => $transaction['app_fee'] ?? null,
            'currency' => $transaction['currency'] ?? null,
            'status' => $status,
            'payment_type' => $transaction['payment_type'] ?? null,
            'narration' => $transaction['narration'] ?? null,
            'customer_name' => $transaction['customer_name'] ?? $transaction['customer']['name'] ?? null,
            'customer_email' => $transaction['customer_email'] ?? $transaction['customer']['email'] ?? null,
            'created_at' => $transaction['created_at'] ?? null,
            'urls' => $routes ? [
                'refund' => $status === 'successful' ? route('flutterwave.transactions.refund', $transaction['id']) : null,
                'resend_webhook' => route('flutterwave.transactions.resend-webhook', $transaction['id']),
            ] : null,
        ];
    }

    /**
     * Get the timeline of a transaction using transactionId
     */
    public function timeline(int|string $transactionId): array
    {
        return $this->client->get("transactions/{$transactionId}/events", [], false);
    }

    /**
     * Generates a Transaction Reference for Payment Request
     */
    public static function generateTransactionReference(string $prefix): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < 5; $i++) {
            $randomString .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $prefix.$randomString.time();
    }
}
