<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Illuminate\Support\Facades\Cache;

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

    /**
     * The country's banks as [['code' => '044', 'name' => 'Access Bank'], ...], sorted by name,
     * for a dropdown. Cached for a day, since bank lists rarely change.
     */
    public function options(string $country = 'NG'): array
    {
        $country = strtoupper($country);

        return Cache::remember("flutterwave:banks:{$country}", 86400, function () use ($country) {
            $banks = [];

            foreach ($this->all($country)['data'] ?? [] as $bank) {
                if (! empty($bank['code']) && ! empty($bank['name'])) {
                    $banks[(string) $bank['code']] = ['code' => (string) $bank['code'], 'name' => trim((string) $bank['name'])];
                }
            }

            usort($banks, static fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

            return $banks;
        });
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
