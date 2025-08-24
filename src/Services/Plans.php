<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Data\Api;
use Flutterwave\Payments\Exception\InvalidArgument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

final class Plans
{
    private Api $api;

    private string $base_url;

    private string $secret_key;

    private LoggerInterface $logger;

    private string $name;
    private string $api_version;

    /**
     * Plans constructor.
     */
    public function __construct(Api $api, array $config)
    {
        $this->api = $api;
        $this->base_url = $this->api::BASE_URL;
        $this->api_version = $this->api::LATEST_VERSION;
        
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
     * Create a payment plan
     */
    public function create(array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/payment-plans";

        $this->logger->info("{$this->name}::Creating payment plan", ['name' => $data['name'] ?? 'unknown']);

        $response = Http::withToken($this->secret_key)->post($url, $data);

        return $response->json();
    }

    /**
     * Get all payment plans
     */
    public function all(?array $filters = null): array
    {
        $url = "{$this->base_url}/{$this->api_version}/payment-plans";
        
        if ($filters) {
            $query = http_build_query($filters);
            $url .= '?' . $query;
        }

        $this->logger->info("{$this->name}::Fetching all payment plans");

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    /**
     * Get a specific payment plan
     */
    public function find(string $planId): array
    {
        $url = "{$this->base_url}/{$this->api_version}/payment-plans/{$planId}";

        $this->logger->info("{$this->name}::Fetching payment plan with ID: {$planId}");

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    /**
     * Update a payment plan
     */
    public function update(string $planId, array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/payment-plans/{$planId}";

        $this->logger->info("{$this->name}::Updating payment plan with ID: {$planId}");

        $response = Http::withToken($this->secret_key)->put($url, $data);

        return $response->json();
    }

    /**
     * Cancel a payment plan
     */
    public function cancel(string $planId): array
    {
        $url = "{$this->base_url}/{$this->api_version}/payment-plans/{$planId}/cancel";

        $this->logger->info("{$this->name}::Cancelling payment plan with ID: {$planId}");

        $response = Http::withToken($this->secret_key)->put($url, []);

        return $response->json();
    }

    /**
     * Create a subscription
     */
    public function subscribe(array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/subscriptions";

        $this->logger->info("{$this->name}::Creating subscription", ['plan' => $data['plan'] ?? 'unknown']);

        $response = Http::withToken($this->secret_key)->post($url, $data);

        return $response->json();
    }

    /**
     * Get all subscriptions
     */
    public function subscriptions(?array $filters = null): array
    {
        $url = "{$this->base_url}/{$this->api_version}/subscriptions";
        
        if ($filters) {
            $query = http_build_query($filters);
            $url .= '?' . $query;
        }

        $this->logger->info("{$this->name}::Fetching all subscriptions");

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    /**
     * Get a specific subscription
     */
    public function subscription(string $subscriptionId): array
    {
        $url = "{$this->base_url}/{$this->api_version}/subscriptions/{$subscriptionId}";

        $this->logger->info("{$this->name}::Fetching subscription with ID: {$subscriptionId}");

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        $url = "{$this->base_url}/{$this->api_version}/subscriptions/{$subscriptionId}/cancel";

        $this->logger->info("{$this->name}::Cancelling subscription with ID: {$subscriptionId}");

        $response = Http::withToken($this->secret_key)->put($url, []);

        return $response->json();
    }

    /**
     * Activate a subscription
     */
    public function activateSubscription(string $subscriptionId): array
    {
        $url = "{$this->base_url}/{$this->api_version}/subscriptions/{$subscriptionId}/activate";

        $this->logger->info("{$this->name}::Activating subscription with ID: {$subscriptionId}");

        $response = Http::withToken($this->secret_key)->put($url, []);

        return $response->json();
    }

    /**
     * Get subscription invoices
     */
    public function invoices(string $subscriptionId): array
    {
        $url = "{$this->base_url}/{$this->api_version}/subscriptions/{$subscriptionId}/invoices";

        $this->logger->info("{$this->name}::Fetching invoices for subscription: {$subscriptionId}");

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    /**
     * Generate subscription link
     */
    public function generateLink(array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/payment-links";

        // Ensure it's marked as a subscription
        $data['is_subscription'] = true;

        $this->logger->info("{$this->name}::Generating subscription link", ['plan' => $data['plan'] ?? 'unknown']);

        $response = Http::withToken($this->secret_key)->post($url, $data);

        return $response->json();
    }

    /**
     * Update subscription
     */
    public function updateSubscription(string $subscriptionId, array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/subscriptions/{$subscriptionId}";

        $this->logger->info("{$this->name}::Updating subscription with ID: {$subscriptionId}");

        $response = Http::withToken($this->secret_key)->put($url, $data);

        return $response->json();
    }

    /**
     * Get plan subscriptions
     */
    public function planSubscriptions(string $planId, ?array $filters = null): array
    {
        $url = "{$this->base_url}/{$this->api_version}/payment-plans/{$planId}/subscriptions";
        
        if ($filters) {
            $query = http_build_query($filters);
            $url .= '?' . $query;
        }

        $this->logger->info("{$this->name}::Fetching subscriptions for plan: {$planId}");

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }
}