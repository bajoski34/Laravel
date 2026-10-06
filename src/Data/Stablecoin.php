<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Data;

/**
 * Networks and coins supported by Flutterwave stablecoin transfers.
 *
 * @see https://developer.flutterwave.com/docs/stablecoins
 */
final class Stablecoin
{
    public const SOLANA = 'SOLANA';

    public const ETHEREUM = 'ETHEREUM';

    public const BASE = 'BASE';

    public const POLYGON = 'POLYGON';

    public const USDT = 'USDT';

    public const USDC = 'USDC';

    public const RLUSD = 'RLUSD';

    /** Coins accepted on each network. */
    public const NETWORKS = [
        self::SOLANA => [self::USDT, self::USDC],
        self::ETHEREUM => [self::USDT, self::USDC, self::RLUSD],
        self::BASE => [self::USDC],
        self::POLYGON => [self::USDT, self::USDC],
    ];

    /** Fiat balances that can be converted into stablecoins. */
    public const FUNDING_CURRENCIES = [Currency::NGN, Currency::USD, Currency::GBP, Currency::EUR, Currency::GHS];

    public static function coins(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::NETWORKS))));
    }

    public static function supports(string $network, string $coin): bool
    {
        return in_array(strtoupper($coin), self::NETWORKS[strtoupper($network)] ?? [], true);
    }

    /**
     * Basic format check so a payout is not sent to an address from the wrong chain.
     */
    public static function isValidAddress(string $network, string $address): bool
    {
        return match (strtoupper($network)) {
            self::ETHEREUM, self::BASE, self::POLYGON => (bool) preg_match('/^0x[a-fA-F0-9]{40}$/', $address),
            self::SOLANA => (bool) preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $address),
            default => false,
        };
    }
}
