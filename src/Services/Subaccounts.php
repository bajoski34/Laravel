<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Data\Api;
use Flutterwave\Payments\Exception\InvalidArgument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

final class Subaccounts
{
    private Api $api;

    private string $base_url;

    private $endpoint;

    private string $secret_key;

    private LoggerInterface $logger;

    private string $name;
    private string $api_version;

    /**
     * Subaccounts constructor.
     */
    public function __construct(Api $api, array $config)
    {
        $this->api = $api;
        $this->base_url = $this->api::BASE_URL;
        $this->api_version = $this->api::LATEST_VERSION;
        $this->endpoint = $this->api::SUBACCOUNTS_ENDPOINT;
        
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
     * Create a subaccount
     */
    public function create(array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/{$this->endpoint}";

        $this->logger->info("{$this->name}::Creating subaccount", ['business_name' => $data['business_name'] ?? 'unknown']);

        $response = Http::withToken($this->secret_key)->post($url, $data);

        return $response->json();
    }

    /**
     * Get all subaccounts
     */
    public function all(?array $filters = null): array
    {
        $url = "{$this->base_url}/{$this->api_version}/{$this->endpoint}";
        
        if ($filters) {
            $query = http_build_query($filters);
            $url .= '?' . $query;
        }

        $this->logger->info("{$this->name}::Fetching all subaccounts");

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    /**
     * Get a specific subaccount
     */
    public function find(string $subaccountId): array
    {
        $url = "{$this->base_url}/{$this->api_version}/{$this->endpoint}{$subaccountId}";

        $this->logger->info("{$this->name}::Fetching subaccount with ID: {$subaccountId}");

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    /**
     * Update a subaccount
     */
    public function update(string $subaccountId, array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/{$this->endpoint}{$subaccountId}";

        $this->logger->info("{$this->name}::Updating subaccount with ID: {$subaccountId}");

        $response = Http::withToken($this->secret_key)->put($url, $data);

        return $response->json();
    }

    /**
     * Delete a subaccount
     */
    public function delete(string $subaccountId): array
    {
        $url = "{$this->base_url}/{$this->api_version}/{$this->endpoint}{$subaccountId}";

        $this->logger->info("{$this->name}::Deleting subaccount with ID: {$subaccountId}");

        $response = Http::withToken($this->secret_key)->delete($url);

        return $response->json();
    }

    /**
     * Create a payout subaccount
     */
    public function createPayout(array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/payout-subaccounts";

        $this->logger->info("{$this->name}::Creating payout subaccount", ['business_name' => $data['business_name'] ?? 'unknown']);

        $response = Http::withToken($this->secret_key)->post($url, $data);

        return $response->json();
    }

    /**
     * Get all payout subaccounts
     */
    public function allPayout(?array $filters = null): array
    {
        $url = "{$this->base_url}/{$this->api_version}/payout-subaccounts";
        
        if ($filters) {
            $query = http_build_query($filters);
            $url .= '?' . $query;
        }

        $this->logger->info("{$this->name}::Fetching all payout subaccounts");

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    /**
     * Get a specific payout subaccount
     */
    public function findPayout(string $subaccountId): array
    {
        $url = "{$this->base_url}/{$this->api_version}/payout-subaccounts/{$subaccountId}";

        $this->logger->info("{$this->name}::Fetching payout subaccount with ID: {$subaccountId}");

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    /**
     * Update a payout subaccount
     */
    public function updatePayout(string $subaccountId, array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/payout-subaccounts/{$subaccountId}";

        $this->logger->info("{$this->name}::Updating payout subaccount with ID: {$subaccountId}");

        $response = Http::withToken($this->secret_key)->put($url, $data);

        return $response->json();
    }

    /**
     * Get available banks for subaccount creation
     */
    public function getBanks(?string $country = null): array
    {
        $url = "{$this->base_url}/{$this->api_version}/banks";
        
        if ($country) {
            $url .= "/{$country}";
        }

        $this->logger->info("{$this->name}::Fetching available banks for country: " . ($country ?? 'all'));

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    /**
     * Validate bank account for subaccount
     */
    public function validateBankAccount(array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/accounts/resolve";

        $this->logger->info("{$this->name}::Validating bank account", ['account_number' => $data['account_number'] ?? 'unknown']);

        $response = Http::withToken($this->secret_key)->post($url, $data);

        return $response->json();
    }

    /**
     * Get subaccount transactions
     */
    public function transactions(string $subaccountId, ?array $filters = null): array
    {
        $url = "{$this->base_url}/{$this->api_version}/{$this->endpoint}{$subaccountId}/transactions";
        
        if ($filters) {
            $query = http_build_query($filters);
            $url .= '?' . $query;
        }

        $this->logger->info("{$this->name}::Fetching transactions for subaccount: {$subaccountId}");

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }
}