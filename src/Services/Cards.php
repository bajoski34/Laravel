<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Exception\InvalidArgument;

/**
 * Card BIN lookups, and charging saved cards with the token from an earlier payment.
 *
 * Get a token by verifying a card payment: it's in `data.card.token`. Tokens are tied to
 * the email used for that payment and last a year. Repeat charges use 3-D Secure unless
 * Flutterwave has enabled no-auth charges on your account, so a charge can stay pending
 * until the customer approves it.
 *
 * @see https://developer.flutterwave.com/docs/tokenization
 */
class Cards extends Service
{
    /**
     * Get the issuer, card type and country for the first six digits of a card.
     */
    public function bin(string $bin): array
    {
        return $this->client->get('card-bins/'.substr(preg_replace('/\D/', '', $bin) ?? '', 0, 6));
    }

    /**
     * Charge a saved card. $email must be the email used when the card was saved.
     * Options: currency and country (default from config), tx_ref (generated), redirect_url
     * (default FLW_REDIRECT_URL), narration, first_name, last_name, payment_plan, subaccounts.
     */
    public function charge(string $token, string $email, int|float|string $amount, array $options = []): array
    {
        return $this->client->post('tokenized-charges', $this->tokenCharge($token, $email, $amount, $options));
    }

    /**
     * Hold $amount on a saved card without charging it. Capture, void or refund it with
     * Flutterwave::preauth() using the response's `data.flw_ref`. Holds are released after 7 days.
     * Pre-authorization has to be enabled on your account by Flutterwave.
     */
    public function preauthorize(string $token, string $email, int|float|string $amount, array $options = []): array
    {
        return $this->charge($token, $email, $amount, [...$options, 'preauthorize' => true]);
    }

    /**
     * Charge many saved cards at once. Each charge needs token, email and amount; the
     * other fields default as in charge(). Bulk charges need no-auth charges enabled.
     * $retryStrategy: retry_interval (minutes), retry_amount_variable (percent of the amount to retry),
     * retry_attempt_variable, last_retry_attempt.
     */
    public function chargeMany(array $charges, array $retryStrategy = [], ?string $title = null): array
    {
        if ($charges === []) {
            throw new InvalidArgument('Pass at least one charge to chargeMany().');
        }

        return $this->client->post('bulk-tokenized-charges', $this->filter([
            'title' => $title,
            'retry_strategy' => [
                'retry_interval' => 120,
                'retry_amount_variable' => 100,
                'retry_attempt_variable' => 2,
                'last_retry_attempt' => 3,
                ...$retryStrategy,
            ],
            'bulk_data' => array_map(function (array $charge) {
                $payload = $this->tokenCharge((string) ($charge['token'] ?? ''), (string) ($charge['email'] ?? ''), $charge['amount'] ?? 0, $charge);
                unset($payload['redirect_url']);

                return $payload;
            }, array_values($charges)),
        ]));
    }

    public function bulkStatus(int|string $bulkId): array
    {
        return $this->client->get("bulk-tokenized-charges/{$bulkId}");
    }

    public function bulkTransactions(int|string $bulkId): array
    {
        return $this->client->get("bulk-tokenized-charges/{$bulkId}/transactions");
    }

    /**
     * Update the customer details on a token, e.g. when they change their email address,
     * so future charges with the new email still work.
     */
    public function updateToken(string $token, string $email, string $phoneNumber, string $firstName, string $lastName): array
    {
        return $this->client->put("tokens/{$token}", [
            'email' => $email,
            'phone_number' => $phoneNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
    }

    private function tokenCharge(string $token, string $email, int|float|string $amount, array $options): array
    {
        if (trim($token) === '') {
            throw new InvalidArgument('A card token is required. Get it from data.card.token when you verify the first payment.');
        }

        if (trim($email) === '') {
            throw new InvalidArgument('The email the card was saved with is required: tokens are tied to it.');
        }

        if (! is_numeric($amount) || $amount <= 0) {
            throw new InvalidArgument('A saved card charge needs an amount greater than zero.');
        }

        unset($options['token'], $options['email'], $options['amount']);

        return $this->filter([
            ...$options,
            'token' => $token,
            'email' => $email,
            'amount' => $amount,
            'currency' => strtoupper((string) ($options['currency'] ?? $this->config['currency'] ?? 'NGN')),
            'country' => strtoupper((string) ($options['country'] ?? $this->config['country'] ?? 'NG')),
            'tx_ref' => $options['tx_ref'] ?? Transactions::generateTransactionReference((string) ($this->config['prefix'] ?? 'LARAVEL-')),
            'redirect_url' => $options['redirect_url'] ?? $this->config['redirect_url'] ?? null,
        ]);
    }
}
