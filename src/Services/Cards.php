<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Data\Api;
use Flutterwave\Payments\Exception\InvalidArgument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

final class Cards
{
    private Api $api;

    private string $base_url;

    private string $secret_key;

    private LoggerInterface $logger;

    private string $name;
    private string $api_version;

    /**
     * Cards constructor.
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
     * Charge a card
     */
    public function charge(array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/charges?type=card";

        $this->logger->info("{$this->name}::Charging card", ['masked_data' => $this->maskSensitiveData($data)]);

        $response = Http::withToken($this->secret_key)->post($url, $data);

        return $response->json();
    }

    /**
     * Validate charge (for OTP validation)
     */
    public function validateCharge(string $flwRef, string $otp): array
    {
        $url = "{$this->base_url}/{$this->api_version}/validate-charge";

        $data = [
            'flw_ref' => $flwRef,
            'otp' => $otp,
        ];

        $this->logger->info("{$this->name}::Validating charge with flw_ref: {$flwRef}");

        $response = Http::withToken($this->secret_key)->post($url, $data);

        return $response->json();
    }

    /**
     * Tokenize a card
     */
    public function tokenize(array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/tokenized-charges";

        $this->logger->info("{$this->name}::Tokenizing card", ['masked_data' => $this->maskSensitiveData($data)]);

        $response = Http::withToken($this->secret_key)->post($url, $data);

        return $response->json();
    }

    /**
     * Charge with token
     */
    public function chargeWithToken(array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/tokenized-charges";

        $this->logger->info("{$this->name}::Charging with token", ['tx_ref' => $data['tx_ref'] ?? 'unknown']);

        $response = Http::withToken($this->secret_key)->post($url, $data);

        return $response->json();
    }

    /**
     * Update token details
     */
    public function updateToken(string $token, array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/tokens/{$token}";

        $this->logger->info("{$this->name}::Updating token: {$token}");

        $response = Http::withToken($this->secret_key)->put($url, $data);

        return $response->json();
    }

    /**
     * Delete a token
     */
    public function deleteToken(string $token): array
    {
        $url = "{$this->base_url}/{$this->api_version}/tokens/{$token}";

        $this->logger->info("{$this->name}::Deleting token: {$token}");

        $response = Http::withToken($this->secret_key)->delete($url);

        return $response->json();
    }

    /**
     * Get card BIN information
     */
    public function binLookup(string $bin): array
    {
        $url = "{$this->base_url}/{$this->api_version}/card-bins/{$bin}";

        $this->logger->info("{$this->name}::Getting BIN information for: {$bin}");

        $response = Http::withToken($this->secret_key)->get($url);

        return $response->json();
    }

    /**
     * Preauthorize a card
     */
    public function preauthorize(array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/charges?type=card";
        
        // Add preauthorize flag
        $data['preauthorize'] = true;

        $this->logger->info("{$this->name}::Preauthorizing card", ['masked_data' => $this->maskSensitiveData($data)]);

        $response = Http::withToken($this->secret_key)->post($url, $data);

        return $response->json();
    }

    /**
     * Capture a preauthorized transaction
     */
    public function capture(string $flwRef, ?float $amount = null): array
    {
        $url = "{$this->base_url}/{$this->api_version}/charges/{$flwRef}/capture";

        $data = [];
        if ($amount !== null) {
            $data['amount'] = $amount;
        }

        $this->logger->info("{$this->name}::Capturing preauthorized transaction: {$flwRef}");

        $response = Http::withToken($this->secret_key)->post($url, $data);

        return $response->json();
    }

    /**
     * Void a preauthorized transaction
     */
    public function void(string $flwRef): array
    {
        $url = "{$this->base_url}/{$this->api_version}/charges/{$flwRef}/void";

        $this->logger->info("{$this->name}::Voiding preauthorized transaction: {$flwRef}");

        $response = Http::withToken($this->secret_key)->post($url, []);

        return $response->json();
    }

    /**
     * Mask sensitive card data for logging
     */
    private function maskSensitiveData(array $data): array
    {
        $masked = $data;
        
        if (isset($masked['card_number'])) {
            $masked['card_number'] = substr($masked['card_number'], 0, 4) . '****' . substr($masked['card_number'], -4);
        }
        
        if (isset($masked['cvv'])) {
            $masked['cvv'] = '***';
        }
        
        if (isset($masked['pin'])) {
            $masked['pin'] = '****';
        }

        return $masked;
    }
}