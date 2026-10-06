<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

/**
 * Wallet balances on your Flutterwave account.
 */
class Balances extends Service
{
    public function all(): array
    {
        return $this->client->get('balances');
    }

    public function currency(string $currency): array
    {
        return $this->client->get('balances/'.strtoupper($currency));
    }
}
