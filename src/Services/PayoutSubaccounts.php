<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Exception\InvalidArgument;

/**
 * Wallets for your customers: each payout subaccount has its own balance and bank account
 * number (nuban) that anyone can pay into. Identify one by its `account_reference`.
 *
 * @see https://developer.flutterwave.com/docs/payout-subaccount
 */
class PayoutSubaccounts extends Service
{
    public function create(string $accountName, string $email, string $country = 'NG', ?string $mobileNumber = null): array
    {
        if (trim($accountName) === '' || trim($email) === '') {
            throw new InvalidArgument('A payout subaccount needs the customer\'s name and email.');
        }

        return $this->client->post('payout-subaccounts', $this->filter([
            'account_name' => $accountName,
            'email' => $email,
            'country' => strtoupper($country),
            'mobilenumber' => $mobileNumber,
        ]));
    }

    /**
     * Filters: email, account_reference, nuban, limit, next_cursor (from data.cursor.next).
     */
    public function all(array $filters = []): array
    {
        return $this->client->get('payout-subaccounts', $filters);
    }

    public function find(string $accountReference): array
    {
        return $this->client->get("payout-subaccounts/{$accountReference}");
    }

    /**
     * Required keys: account_name, email, mobilenumber. Optional: country.
     */
    public function update(string $accountReference, array $data): array
    {
        return $this->client->put("payout-subaccounts/{$accountReference}", $data);
    }

    public function balance(string $accountReference, ?string $currency = null): array
    {
        return $this->client->get("payout-subaccounts/{$accountReference}/balances", $this->filter(['currency' => $currency ? strtoupper($currency) : null]));
    }

    /**
     * Credits and debits between two dates (YYYY-MM-DD). Filters: currency, page, fetch_limit.
     */
    public function transactions(string $accountReference, string $from, string $to, array $filters = []): array
    {
        return $this->client->get("payout-subaccounts/{$accountReference}/transactions", ['from' => $from, 'to' => $to, ...$filters]);
    }

    /**
     * The wallet's bank account details for funding it. Pass $all to include every
     * static account, not just the primary one.
     */
    public function staticAccount(string $accountReference, bool $all = false): array
    {
        return $this->client->get("payout-subaccounts/{$accountReference}/static-account", $this->filter(['currency' => 'NGN', 'verbose' => $all ? 1 : null]));
    }

    /**
     * Deactivate the wallet. Flutterwave processes this in the background.
     */
    public function delete(string $accountReference): array
    {
        return $this->client->delete("payout-subaccounts/{$accountReference}");
    }
}
