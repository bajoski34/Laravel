<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Data\Api;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

final class Transactions
{
    private Api $api;

    private string $base_url;

    private $endpoint;

    private string $secret_key;

    private LoggerInterface $logger;

    private string $name;
    private string $api_version;

    /**
     * Transactions constructor.
     */
    public function __construct(Api $api, array $config)
    {
        $this->api = $api;
        $this->base_url = $this->api::BASE_URL;
        $this->api_version = $this->api::LATEST_VERSION;
        $this->endpoint = $this->api::TRANSACTIONS_ENDPOINT;
        
        $this->handleMissingSecretKey( $config );
        $this->secret_key = $config['secret_key'];
        $this->name = self::class;
        $this->logger = Log::channel('flutterwave');
    }
    
    private function handleMissingSecretKey( $config ): void {
        if( !isset( $config['secret_key'] )) {
            throw new InvalidArgument('The secret key is required. please add it to the .env file.');
        }
    }

    /**
     * Verify a transaction with transactionId
     */
    public function verify(string $transactionId): mixed
    {
        $url = "{$this->base_url}/{$this->api_version}/{$this->endpoint}{$transactionId}/verify";

        $this->logger->info("{$this->name}::Verifying transaction with id: ".$transactionId);
        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    /**
     * Verify a transaction with tx_ref
     */
    public function verifyTransactionReference(string $tx_ref): mixed
    {
        $url = "{$this->base_url}/{$this->api_version}/{$this->endpoint}verify_by_reference?tx_ref={$tx_ref}";

        $this->logger->info("{$this->name}::Verifying transaction with reference: ".$tx_ref);

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    /**
     * Refund a transaction
     */
    public function refund(string $transactionId, ?string $amount = null): mixed
    {
        $url = "{$this->base_url}/{$this->api_version}/{$this->endpoint}{$transactionId}/refund";

        $payload = is_null($amount) ? [] : ['amount' => $amount];

        $this->logger->info("{$this->name}::Refunding transaction with id: ".$transactionId);

        $response = Http::withToken($this->secret_key)->post($url, $payload);

        return $response->json();
    }

    /**
     * Get all the transactions on your account. pass filters as  an array
     *
     * @param array|null $data
     */
    public function all(?array $data = null): mixed
    {
        $url = "{$this->base_url}/{$this->api_version}/{$this->endpoint}";
        $query = is_null($data) ? '' : http_build_query($data);
        $url .= '?'.$query;

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    /**
     * Get transaction fee by supplying amount and currency
     *
     * @param array $data
     * @return array
     */
    public function fees(array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/{$this->endpoint}fees";
        $query = http_build_query($data);
        $url .= $query;
        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    /**
     * Send Failed Transaction Webhooks
     *
     * @return array
     */
    public function resendFailedHooks(string $transactionId, int $wait = 0): array
    {
        $url = "{$this->base_url}/{$this->api_version}/{$this->endpoint}{$transactionId}/resend-hooks";

        $payload = ['wait' => $wait];

        $response = Http::withToken($this->secret_key)->post($url, $payload);

        return $response->json();
    }

    /**
     * Get the timeline of a transaction using trasnactionId
     *
     * @return array
     */
    public function timeline(string $transactionId): array
    {
        $url = "{$this->base_url}/{$this->api_version}/{$this->endpoint}{$transactionId}/events";

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    /**
     * Generates a Transaction Reference for Payment Request
     */
    public static function generateTransactionReference(string $prefix): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < 5; $i++) {
            $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
        }

        return $prefix.$randomString.time();
    }
}
