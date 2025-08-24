<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Data\Api;
use Flutterwave\Payments\Exception\InvalidArgument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

final class MobileMoney
{
    private Api $api;

    private string $base_url;

    private string $secret_key;

    private LoggerInterface $logger;

    private string $name;
    private string $api_version;

    /**
     * MobileMoney constructor.
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
     * Charge via mobile money
     */
    public function charge(array $data): array
    {
        $url = "{$this->base_url}/{$this->api_version}/charges";

        // Ensure mobile money type is set
        $data['type'] = $this->determineMobileMoneyType($data);

        $this->logger->info("{$this->name}::Charging via mobile money", [
            'type' => $data['type'],
            'phone_number' => $data['phone_number'] ?? 'unknown'
        ]);

        $response = Http::withToken($this->secret_key)->post($url, $data);

        return $response->json();
    }

    /**
     * Rwanda Mobile Money (MTN, Airtel)
     */
    public function rwanda(array $data): array
    {
        $data['type'] = 'mobilemoneyrwanda';
        return $this->charge($data);
    }

    /**
     * Ghana Mobile Money
     */
    public function ghana(array $data): array
    {
        $data['type'] = 'mobilemoneyghana';
        return $this->charge($data);
    }

    /**
     * Uganda Mobile Money
     */
    public function uganda(array $data): array
    {
        $data['type'] = 'mobilemoneyuganda';
        return $this->charge($data);
    }

    /**
     * Kenya Mobile Money (M-Pesa)
     */
    public function kenya(array $data): array
    {
        $data['type'] = 'mpesa';
        return $this->charge($data);
    }

    /**
     * Zambia Mobile Money
     */
    public function zambia(array $data): array
    {
        $data['type'] = 'mobilemoneyzambia';
        return $this->charge($data);
    }

    /**
     * Tanzania Mobile Money
     */
    public function tanzania(array $data): array
    {
        $data['type'] = 'mobilemoneytanzania';
        return $this->charge($data);
    }

    /**
     * Francophone Mobile Money
     */
    public function francophone(array $data): array
    {
        $data['type'] = 'mobilemoneyfranco';
        return $this->charge($data);
    }

    /**
     * Get mobile money networks for a country
     */
    public function getNetworks(string $country): array
    {
        $networks = [
            'NG' => ['mtn', 'airtel', 'glo', '9mobile'],
            'GH' => ['mtn', 'vodafone', 'airteltigo'],
            'KE' => ['mpesa'],
            'UG' => ['mtn', 'airtel'],
            'RW' => ['mtn', 'airtel'],
            'ZM' => ['mtn', 'airtel'],
            'TZ' => ['vodacom', 'tigo', 'airtel'],
        ];

        return $networks[$country] ?? [];
    }

    /**
     * Validate phone number format for mobile money
     */
    public function validatePhoneNumber(string $phoneNumber, string $country): bool
    {
        // Remove any non-numeric characters except +
        $phone = preg_replace('/[^\d+]/', '', $phoneNumber);

        $patterns = [
            'NG' => '/^(\+234|234|0)?[789]\d{9}$/',
            'GH' => '/^(\+233|233|0)?[2459]\d{8}$/',
            'KE' => '/^(\+254|254|0)?[17]\d{8}$/',
            'UG' => '/^(\+256|256|0)?[37]\d{8}$/',
            'RW' => '/^(\+250|250|0)?[78]\d{8}$/',
            'ZM' => '/^(\+260|260|0)?[79]\d{8}$/',
            'TZ' => '/^(\+255|255|0)?[67]\d{8}$/',
        ];

        $pattern = $patterns[$country] ?? '/^\+?[\d\s\-\(\)]+$/';

        return preg_match($pattern, $phone) === 1;
    }

    /**
     * Format phone number for mobile money transaction
     */
    public function formatPhoneNumber(string $phoneNumber, string $country): string
    {
        // Remove any non-numeric characters except +
        $phone = preg_replace('/[^\d+]/', '', $phoneNumber);

        $countryPrefixes = [
            'NG' => '234',
            'GH' => '233',
            'KE' => '254',
            'UG' => '256',
            'RW' => '250',
            'ZM' => '260',
            'TZ' => '255',
        ];

        $prefix = $countryPrefixes[$country] ?? '';

        // If phone starts with country prefix, use as is
        if (str_starts_with($phone, '+' . $prefix) || str_starts_with($phone, $prefix)) {
            return $phone;
        }

        // If phone starts with 0, replace with country prefix
        if (str_starts_with($phone, '0')) {
            return $prefix . substr($phone, 1);
        }

        // Otherwise, prepend country prefix
        return $prefix . $phone;
    }

    /**
     * Determine mobile money type based on data
     */
    private function determineMobileMoneyType(array $data): string
    {
        if (isset($data['type'])) {
            return $data['type'];
        }

        // Try to determine from currency or country
        $currency = $data['currency'] ?? '';
        
        $typeMap = [
            'RWF' => 'mobilemoneyrwanda',
            'GHS' => 'mobilemoneyghana',
            'UGX' => 'mobilemoneyuganda',
            'KES' => 'mpesa',
            'ZMW' => 'mobilemoneyzambia',
            'TZS' => 'mobilemoneytanzania',
        ];

        return $typeMap[$currency] ?? 'mobilemoneyghana'; // Default fallback
    }
}