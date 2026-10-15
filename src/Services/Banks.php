<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

/**
 * Bank lists and account-name lookups.
 *
 * @see https://developer.flutterwave.com/reference/get-all-banks
 */
class Banks extends Service
{
    /**
     * List banks for a country code, e.g. NG, GH, KE, UG, ZA, TZ.
     */
    public function all(string $country = 'NG'): array
    {
        return $this->client->get('banks/'.strtoupper($country));
    }

    public function branches(int|string $bankId): array
    {
        return $this->client->get("banks/{$bankId}/branches");
    }

    /**
     * Resolve the account name for a Nigerian or Ghanaian bank account, or a
     * Ghanaian mobile money number (pass the phone number and the provider's code).
     *
     * @see https://developer.flutterwave.com/docs/bank-account-verification-1
     */
    public function resolveAccount(string $accountNumber, string $bankCode, ?string $country = null): array
    {
        return $this->client->post('accounts/resolve', $this->filter([
            'account_number' => $accountNumber,
            'account_bank' => $bankCode,
            'country' => $country === null ? null : strtoupper($country),
        ]));
    }

    /**
     * Resolve the business name behind a Flutterwave merchant ID, e.g. before a wallet-to-wallet transfer.
     */
    public function resolveMerchant(string $merchantId): array
    {
        return $this->resolveAccount($merchantId, 'flutterwave');
    }
}
