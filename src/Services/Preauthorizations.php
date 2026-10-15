<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Exception\InvalidArgument;

/**
 * Settle card holds made with cards()->preauthorize(), by the charge's `data.flw_ref`.
 * A hold you don't capture within 7 days is released automatically.
 *
 * @see https://developer.flutterwave.com/docs/preauthorization
 */
class Preauthorizations extends Service
{
    /**
     * Charge some or all of the held amount. You can capture several times, as long as
     * the total stays within the amount held.
     */
    public function capture(string $flwRef, int|float|string $amount): array
    {
        return $this->client->post("charges/{$flwRef}/capture", ['amount' => $this->amount($amount, 'capture')]);
    }

    /**
     * Release the hold without charging the customer.
     */
    public function void(string $flwRef): array
    {
        return $this->client->post("charges/{$flwRef}/void");
    }

    /**
     * Give back money you already captured.
     */
    public function refund(string $flwRef, int|float|string $amount): array
    {
        return $this->client->post("charges/{$flwRef}/refund", ['amount' => $this->amount($amount, 'refund')]);
    }

    private function amount(int|float|string $amount, string $action): string
    {
        if (! is_numeric($amount) || $amount <= 0) {
            throw new InvalidArgument("The amount to {$action} must be greater than zero.");
        }

        return (string) $amount;
    }
}
