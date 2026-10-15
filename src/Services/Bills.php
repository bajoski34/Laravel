<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Exception\InvalidArgument;

/**
 * Airtime, data, electricity, cable TV and other bills, paid from your Flutterwave balance.
 *
 * Find what to pay in three steps: categories() gives category codes (AIRTIME, UTILITYBILLS...),
 * billers($category) gives biller codes, and items($billerCode) gives item codes and prices.
 * Validate the customer's meter, smartcard or phone number before paying: payments can't be undone.
 *
 * @see https://developer.flutterwave.com/docs/bill-payments
 */
class Bills extends Service
{
    public function categories(string $country = 'NG'): array
    {
        return $this->client->get('top-bill-categories', ['country' => strtoupper($country)]);
    }

    public function billers(string $category, string $country = 'NG'): array
    {
        return $this->client->get('bills/'.strtoupper($category).'/billers', ['country' => strtoupper($country)]);
    }

    /**
     * What the biller sells, with item_code, amount, fee and the customer number format (reg_expression, label_name).
     */
    public function items(string $billerCode): array
    {
        return $this->client->get("billers/{$billerCode}/items");
    }

    /**
     * Check a customer number (meter, smartcard, phone...) and get the name on it. `data.minimum` and
     * `data.maximum` are the provider's limits when they're not zero.
     */
    public function validate(string $itemCode, string $customer): array
    {
        return $this->client->get("bill-items/{$itemCode}/validate", ['customer' => $customer]);
    }

    /**
     * Pay a bill. A unique `reference` is generated when you don't pass one, so you can look it up later.
     */
    public function pay(string $billerCode, string $itemCode, string $customerId, int|float|string $amount, array $options = []): array
    {
        if (! is_numeric($amount) || $amount <= 0) {
            throw new InvalidArgument('A bill payment needs an amount greater than zero.');
        }

        if (trim($customerId) === '') {
            throw new InvalidArgument('A bill payment needs the customer number: the phone number for airtime, the smartcard or meter number for others.');
        }

        return $this->client->post("billers/{$billerCode}/items/{$itemCode}/payment", $this->filter([
            'country' => strtoupper((string) ($options['country'] ?? $this->config['country'] ?? 'NG')),
            'customer_id' => $customerId,
            'amount' => $amount,
            'reference' => $options['reference'] ?? Transactions::generateTransactionReference((string) ($this->config['prefix'] ?? 'LARAVEL-')),
            'callback_url' => $options['callback_url'] ?? null,
        ]));
    }

    /**
     * Validate the customer number first and only pay when it checks out and the amount is
     * within the provider's limits. Returns the payment response.
     */
    public function payValidated(string $billerCode, string $itemCode, string $customerId, int|float|string $amount, array $options = []): array
    {
        $check = $this->validate($itemCode, $customerId)['data'] ?? [];

        if (($check['response_code'] ?? null) !== '00') {
            throw new InvalidArgument("Flutterwave couldn't confirm the customer number {$customerId}: ".($check['response_message'] ?? 'check it and try again.'));
        }

        $minimum = (float) ($check['minimum'] ?? 0);
        $maximum = (float) ($check['maximum'] ?? 0);

        if (($minimum > 0 && $amount < $minimum) || ($maximum > 0 && $amount > $maximum)) {
            throw new InvalidArgument("The amount must be between {$minimum} and {$maximum} for this bill.");
        }

        return $this->pay($billerCode, $itemCode, $customerId, $amount, $options);
    }

    /**
     * Look up a bill payment by its reference (the tx_ref in the payment response).
     */
    public function status(string $reference): array
    {
        return $this->client->get("bills/{$reference}", ['verbose' => 1]);
    }

    /**
     * Filters: status (Successful, Failed, Reversed), from, to, page, limit, reference, category, country.
     */
    public function history(array $filters = []): array
    {
        return $this->client->get('bills/history', $filters);
    }
}
