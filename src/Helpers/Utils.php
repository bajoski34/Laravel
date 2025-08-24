<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Helpers;

use Flutterwave\Payments\Data\Currency;

final class Utils
{
    /**
     * Validate email address
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate amount
     */
    public static function validateAmount(float $amount): bool
    {
        return $amount > 0;
    }

    /**
     * Format amount to 2 decimal places
     */
    public static function formatAmount(float $amount): float
    {
        return round($amount, 2);
    }

    /**
     * Generate a random transaction reference
     */
    public static function generateReference(string $prefix = 'FLW'): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        
        for ($i = 0; $i < 10; $i++) {
            $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
        }

        return $prefix . '_' . $randomString . '_' . time();
    }

    /**
     * Convert amount to kobo/cents based on currency
     */
    public static function convertToSubunit(float $amount, string $currency): int
    {
        // Currencies that use subunits (kobo, cents, etc.)
        $subunitCurrencies = [
            Currency::NGN => 100, // kobo
            Currency::USD => 100, // cents
            Currency::GBP => 100, // pence
            Currency::EUR => 100, // cents
            Currency::GHS => 100, // pesewas
            Currency::KES => 100, // cents
            Currency::ZAR => 100, // cents
            // Add more as needed
        ];

        $multiplier = $subunitCurrencies[$currency] ?? 1;
        
        return (int) ($amount * $multiplier);
    }

    /**
     * Convert from subunit to main unit
     */
    public static function convertFromSubunit(int $amount, string $currency): float
    {
        $subunitCurrencies = [
            Currency::NGN => 100,
            Currency::USD => 100,
            Currency::GBP => 100,
            Currency::EUR => 100,
            Currency::GHS => 100,
            Currency::KES => 100,
            Currency::ZAR => 100,
        ];

        $divisor = $subunitCurrencies[$currency] ?? 1;
        
        return $amount / $divisor;
    }

    /**
     * Get currency symbol
     */
    public static function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            Currency::NGN => '₦',
            Currency::USD => '$',
            Currency::GBP => '£',
            Currency::EUR => '€',
            Currency::GHS => '₵',
            Currency::KES => 'KSh',
            Currency::ZAR => 'R',
            'XAF' => 'FCFA',
            'XOF' => 'CFA',
            'RWF' => 'RF',
            'UGX' => 'USh',
            'TZS' => 'TSh',
            'ZMW' => 'ZK',
        ];

        return $symbols[$currency] ?? $currency;
    }

    /**
     * Format currency amount with symbol
     */
    public static function formatCurrency(float $amount, string $currency): string
    {
        $symbol = self::getCurrencySymbol($currency);
        $formattedAmount = number_format($amount, 2);
        
        return "{$symbol}{$formattedAmount}";
    }

    /**
     * Validate transaction reference format
     */
    public static function validateTransactionReference(string $txRef): bool
    {
        // Basic validation - should be at least 3 characters long and contain alphanumeric characters
        return preg_match('/^[a-zA-Z0-9_-]{3,}$/', $txRef) === 1;
    }

    /**
     * Mask sensitive data for logging
     */
    public static function maskSensitiveData(array $data): array
    {
        $sensitiveFields = ['card_number', 'cvv', 'pin', 'account_number', 'phone_number'];
        $masked = $data;

        foreach ($sensitiveFields as $field) {
            if (isset($masked[$field])) {
                $value = $masked[$field];
                $length = strlen($value);
                
                if ($length <= 4) {
                    $masked[$field] = str_repeat('*', $length);
                } else {
                    $masked[$field] = substr($value, 0, 2) . str_repeat('*', $length - 4) . substr($value, -2);
                }
            }
        }

        return $masked;
    }

    /**
     * Get supported countries and their currencies
     */
    public static function getSupportedCountries(): array
    {
        return [
            'NG' => ['name' => 'Nigeria', 'currency' => Currency::NGN],
            'GH' => ['name' => 'Ghana', 'currency' => Currency::GHS],
            'KE' => ['name' => 'Kenya', 'currency' => Currency::KES],
            'UG' => ['name' => 'Uganda', 'currency' => 'UGX'],
            'RW' => ['name' => 'Rwanda', 'currency' => 'RWF'],
            'ZA' => ['name' => 'South Africa', 'currency' => Currency::ZAR],
            'TZ' => ['name' => 'Tanzania', 'currency' => 'TZS'],
            'ZM' => ['name' => 'Zambia', 'currency' => 'ZMW'],
            'US' => ['name' => 'United States', 'currency' => Currency::USD],
            'GB' => ['name' => 'United Kingdom', 'currency' => Currency::GBP],
        ];
    }

    /**
     * Get country by currency
     */
    public static function getCountryByCurrency(string $currency): ?string
    {
        $countries = self::getSupportedCountries();
        
        foreach ($countries as $code => $info) {
            if ($info['currency'] === $currency) {
                return $code;
            }
        }
        
        return null;
    }

    /**
     * Check if currency is supported
     */
    public static function isCurrencySupported(string $currency): bool
    {
        $supportedCurrencies = array_column(self::getSupportedCountries(), 'currency');
        return in_array($currency, $supportedCurrencies);
    }
}