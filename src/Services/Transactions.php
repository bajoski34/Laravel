<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Data\Api;
use Flutterwave\Payments\Exception\InvalidArgument;
use Illuminate\Support\Facades\Log;
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
     */
    public function refund(int|string $transactionId, int|float|string|null $amount = null): array
    {
        $this->logger->info("{$this->name}::Refunding transaction with id: ".$transactionId);

        return $this->client->post("transactions/{$transactionId}/refund", is_null($amount) ? [] : ['amount' => $amount], false);
    }

    /**
     * Get all the transactions on your account. pass filters as an array
     */
    public function all(?array $data = null): array
    {
        return $this->client->get('transactions', $data ?? [], false);
    }

    /**
     * Get transaction fee by supplying amount and currency
     */
    public function fees(array $data): array
    {
        return $this->client->get('transactions/fee', $data, false);
    }

    /**
     * Send Failed Transaction Webhooks
     */
    public function resendFailedHooks(int|string $transactionId, int $wait = 0): array
    {
        return $this->client->post("transactions/{$transactionId}/resend-hook", ['wait' => $wait], false);
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
