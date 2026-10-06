<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Data\Stablecoin;
use Flutterwave\Payments\Exception\InvalidArgument;

/**
 * Send USDT, USDC and RLUSD to wallets, and fund stablecoin balances from fiat.
 *
 * Requires a live, production-approved account with whitelisted IPs.
 *
 * @see https://developer.flutterwave.com/docs/stablecoins
 */
class Stablecoins extends Service
{
    /**
     * Send stablecoins to a wallet address.
     *
     * The fee is deducted from $amount when paying from a stablecoin balance.
     * Pass a fiat $debitCurrency (NGN, USD, GBP, EUR, GHS) to convert and pay in one step;
     * the fee is then charged to that fiat balance.
     */
    public function send(string $network, string $address, int|float $amount, string $coin, ?string $debitCurrency = null, array $extra = []): array
    {
        $network = strtoupper($network);
        $coin = strtoupper($coin);
        $address = trim($address);

        if (! Stablecoin::supports($network, $coin)) {
            throw new InvalidArgument("{$coin} cannot be sent on {$network}. Supported: ".$this->describeNetworks());
        }

        if (! Stablecoin::isValidAddress($network, $address)) {
            throw new InvalidArgument("\"{$address}\" is not a valid {$network} wallet address.");
        }

        $debitCurrency = strtoupper($debitCurrency ?? $coin);
        if ($debitCurrency !== $coin) {
            $this->assertFundingCurrency($debitCurrency);
        }

        return $this->client->post('transfers', array_merge([
            'account_bank' => $network,
            'account_number' => $address,
            'amount' => $amount,
            'currency' => $coin,
            'debit_currency' => $debitCurrency,
            'reference' => Transactions::generateTransactionReference((string) ($this->config['prefix'] ?? 'LARAVEL-')),
        ], $extra));
    }

    /**
     * Convert a fiat balance into your stablecoin wallet.
     *
     * $merchantId defaults to FLW_MERCHANT_ID (shown on your dashboard).
     */
    public function fund(int|float $amount, string $coin, string $fromCurrency, ?string $merchantId = null, array $extra = []): array
    {
        $coin = strtoupper($coin);
        $fromCurrency = strtoupper($fromCurrency);
        $merchantId ??= $this->config['merchant_id'] ?? null;

        if (! in_array($coin, Stablecoin::coins(), true)) {
            throw new InvalidArgument("{$coin} is not a supported stablecoin. Use one of: ".implode(', ', Stablecoin::coins()));
        }

        $this->assertFundingCurrency($fromCurrency);

        if (empty($merchantId)) {
            throw new InvalidArgument('Your Flutterwave Merchant ID is required to fund a stablecoin wallet. Add FLW_MERCHANT_ID to your .env file.');
        }

        return $this->client->post('transfers', array_merge([
            'account_bank' => 'flutterwave',
            'account_number' => (string) $merchantId,
            'amount' => $amount,
            'currency' => $coin,
            'debit_currency' => $fromCurrency,
            'reference' => Transactions::generateTransactionReference((string) ($this->config['prefix'] ?? 'LARAVEL-')),
        ], $extra));
    }

    /**
     * Get the fee for a stablecoin transfer. Pass a fiat $debitCurrency for conversion fees.
     */
    public function fee(int|float $amount, string $coin, ?string $debitCurrency = null): array
    {
        return $this->client->get('transfers/fee', $this->filter([
            'amount' => $amount,
            'currency' => strtoupper($coin),
            'type' => 'crypto',
            'debit_currency' => $debitCurrency ? strtoupper($debitCurrency) : null,
        ]));
    }

    /**
     * Fetch a stablecoin transfer to check its status.
     */
    public function find(int|string $id): array
    {
        return $this->client->get("transfers/{$id}");
    }

    /**
     * Your stablecoin wallet balances, keyed by coin.
     *
     * @return array<string, array{currency: string, available_balance: float|int, ledger_balance: float|int}>
     */
    public function balances(): array
    {
        $balances = [];

        foreach ($this->client->get('balances')['data'] ?? [] as $balance) {
            if (in_array($balance['currency'] ?? null, Stablecoin::coins(), true)) {
                $balances[$balance['currency']] = $balance;
            }
        }

        return $balances;
    }

    private function assertFundingCurrency(string $currency): void
    {
        if (! in_array($currency, Stablecoin::FUNDING_CURRENCIES, true)) {
            throw new InvalidArgument('Stablecoins can only be funded from '.implode(', ', Stablecoin::FUNDING_CURRENCIES)." balances, not {$currency}.");
        }
    }

    private function describeNetworks(): string
    {
        return implode('; ', array_map(
            static fn (string $network, array $coins) => $network.' ('.implode(', ', $coins).')',
            array_keys(Stablecoin::NETWORKS),
            Stablecoin::NETWORKS
        ));
    }
}
