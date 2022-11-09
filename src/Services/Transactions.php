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

    /**
     * Transactions constructor.
     */
    public function __construct(Api $api, array $config)
    {
        $this->api = $api;
        $this->base_url = $this->api::BASE_URL;
        $this->endpoint = $this->api::TRANSACTIONS_ENDPOINT;
        $this->secret_key = $config['secret_key'];
        $this->logger = Log::channel('flutterwave');
        $this->name = self::class;
    }

    public function verify(string $transactionId)
    {
        $url = $this->base_url . $this->endpoint . $transactionId.'/verify';

        $this->logger->info("{$this->name}::Verifying transaction with id: " . $transactionId);
        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    public function verifyTransactionReference(string $tx_ref)
    {
        $url = $this->base_url . $this->endpoint . "verify_by_reference?tx_ref={$tx_ref}";

        $this->logger->info("{$this->name}::Verifying transaction with reference: " . $tx_ref);

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    public function refund(string $transactionId, ?string $amount = null)
    {
        $url = $this->base_url . $this->endpoint . $transactionId.'/refund';

        $payload = is_null($amount) ? [] : ['amount' => $amount];

        $this->logger->info("{$this->name}::Refunding transaction with id: " . $transactionId);

        $response = Http::withToken($this->secret_key)->post($url, $payload);

        return $response->json();
    }

    public function all(?array $data = null)
    {
        $url = $this->base_url . $this->endpoint;

        $query = is_null($data) ? '' : http_build_query($data);
        $url .= '?'.$query;

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    public function fees(array $data)
    {
        $url = $this->base_url . $this->endpoint . 'fees';
        $query = http_build_query($data);
        $url .= $query;
        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    public function resendFailedHooks(string $transactionId, int $wait = 0)
    {
        $url = $this->base_url . $this->endpoint . $transactionId.'/resend-hooks';

        $payload = ['wait' => $wait];

        $response = Http::withToken($this->secret_key)->post($url, $payload);

        return $response->json();
    }

    /**
     * @return array
     */
    public function timeline(string $transactionId): array
    {
        $url = $this->base_url . $this->endpoint . $transactionId.'/events';

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }
}
