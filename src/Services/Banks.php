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
     * Resolve the account name for an account number and bank code.
     */
    public function resolveAccount(string $accountNumber, string $bankCode): array
    {
        return $this->client->post('accounts/resolve', [
            'account_number' => $accountNumber,
            'account_bank' => $bankCode,
        ]);
    }
}
