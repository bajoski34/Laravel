<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

/**
 * Card BIN lookups.
 *
 * @see https://developer.flutterwave.com/reference/verify-card-bins
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
}
