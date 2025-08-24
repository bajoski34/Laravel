<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Data\Api;
use Flutterwave\Payments\Exception\InvalidArgument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

final class Transfers
{
    private Api $api;

    private string $base_url;

    private $endpoint;

    private string $secret_key;

    private LoggerInterface $logger;

    private string $name;
    private string $api_version;

    /**
     * Transfers constructor.
     */
    public function __construct(Api $api, array $config)
    {
        $this->api = $api;
        $this->base_url = $this->api::BASE_URL;
        $this->api_version = $this->api::LATEST_VERSION;
        $this->endpoint = $this->api::TRANSFERS_ENDPOINT;
        
        $this->handleMissingSecretKey($config);
        $this->secret_key = $config['secret_key'];
        $this->name = self::class;
        $this->logger = Log::channel('flutterwave');
    }

    private function handleMissingSecretKey($config): void
    {
        if (!isset($config['secret_key']) || empty($config['secret_key'])) {
            throw new InvalidArgument('The secret key is required. please add it to the .env file.');
        }
    }

    /**
     * Create a bank transfer
     */
    public function bank(array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/{$this->endpoint}";

        $this->logger->info("{$this->name}::Creating bank transfer", $data);

        $response = Http::withToken($this->secret_key)->post($url, $data);

        return $response->json();
    }

    /**
     * Create a mobile money transfer
     */
    public function mobileMoney(array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/{$this->endpoint}";

        $this->logger->info("{$this->name}::Creating mobile money transfer", $data);

        $response = Http::withToken($this->secret_key)->post($url, $data);

        return $response->json();
    }

    /**
     * Get all transfers
     */
    public function all(?array $filters = null): array
    {
        $url = "{$this->base_url}/{$this->api_version}/{$this->endpoint}";
        
        if ($filters) {
            $query = http_build_query($filters);
            $url .= '?' . $query;
        }

        $this->logger->info("{$this->name}::Fetching all transfers");

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    /**
     * Get a single transfer by ID
     */
    public function find(string $transferId): array
    {
        $url = "{$this->base_url}/{$this->api_version}/{$this->endpoint}{$transferId}";

        $this->logger->info("{$this->name}::Fetching transfer with ID: {$transferId}");

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    /**
     * Get transfer fee
     */
    public function fee(array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/{$this->endpoint}fee";

        $this->logger->info("{$this->name}::Getting transfer fee", $data);

        $response = Http::withToken($this->secret_key)->post($url, $data);

        return $response->json();
    }

    /**
     * Retry a failed transfer
     */
    public function retry(string $transferId): array
    {
        $url = "{$this->base_url}/{$this->api_version}/{$this->endpoint}{$transferId}/retries";

        $this->logger->info("{$this->name}::Retrying transfer with ID: {$transferId}");

        $response = Http::withToken($this->secret_key)->post($url, []);

        return $response->json();
    }

    /**
     * Get transfer rates
     */
    public function rates(array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/transfers/rates";

        $this->logger->info("{$this->name}::Getting transfer rates", $data);

        $query = http_build_query($data);
        $url .= '?' . $query;

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    /**
     * Get wallet balance
     */
    public function walletBalance(?string $currency = null): array
    {
        $url = "{$this->base_url}/{$this->api_version}/balances";

        if ($currency) {
            $url .= "/{$currency}";
        }

        $this->logger->info("{$this->name}::Getting wallet balance for currency: " . ($currency ?? 'all'));

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }
}