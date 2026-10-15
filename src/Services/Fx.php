<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Exception\InvalidArgument;

/**
 * Convert between currencies at a quoted rate: request a quote, wait for it to
 * be READY, then trade it. FX must be enabled on your account, trades run on
 * weekdays only, and the minimum is USD 1,000 or its equivalent.
 *
 * Quote updates arrive as `quote.completed` webhooks (the QuoteCompleted event)
 * and trade results as `transfer.completed` (TransferCompleted).
 *
 * @see https://developer.flutterwave.com/docs/fx-trade
 */
class Fx extends Service
{
    /** Quote statuses: NEW, then PROCESSING, then READY, EXPIRED (after 5 minutes) or FAILED. */
    public const QUOTE_READY = 'READY';

    /** Trade statuses: NEW, then PENDING, then SETTLED or FAILED. */
    public const TRADE_SETTLED = 'SETTLED';

    /** The API reference documents POST /trades; its guide's example uses /trade. */
    private const TRADE_ENDPOINT = 'trades';

    /**
     * Ask for a rate to sell $quantity of $baseCurrency for $targetCurrency,
     * e.g. quote('NGN', 'USD', 2_000_000) to spend NGN 2,000,000 on dollars.
     * The quote starts as NEW; fetch it with findQuote() or wait for the webhook.
     */
    public function quote(string $baseCurrency, string $targetCurrency, int|float|string $quantity, ?string $reference = null): array
    {
        if (! is_numeric($quantity) || $quantity <= 0) {
            throw new InvalidArgument('An FX quote needs a quantity greater than zero.');
        }

        $baseCurrency = strtoupper($baseCurrency);
        $targetCurrency = strtoupper($targetCurrency);

        if ($baseCurrency === $targetCurrency) {
            throw new InvalidArgument("An FX quote needs two different currencies, not {$baseCurrency} twice.");
        }

        return $this->client->post('rfq', $this->filter([
            'base_currency' => $baseCurrency,
            'target_currency' => $targetCurrency,
            'quantity' => $quantity,
            'reference' => $reference,
        ]));
    }

    /**
     * When READY, `data.quote` holds best_available_rate, approved_quantity (may be
     * less than you asked for), total_value and expiry.
     */
    public function findQuote(string $quoteId): array
    {
        return $this->client->get("rfq/{$quoteId}");
    }

    /**
     * Execute a READY quote. Each quote can only be traded once.
     */
    public function trade(string $quoteId, string $narration, array $meta = []): array
    {
        if (trim($narration) === '') {
            throw new InvalidArgument('Flutterwave needs a narration describing the trade, for your records.');
        }

        return $this->client->post(self::TRADE_ENDPOINT, $this->filter([
            'quote_id' => $quoteId,
            'narration' => $narration,
            'meta' => $meta === [] ? null : $meta,
        ]));
    }

    public function findTrade(string $tradeId): array
    {
        return $this->client->get("trade/{$tradeId}");
    }

    /**
     * Fetch the quote and trade it only if it is still READY, so an expired quote
     * fails here with a clear message instead of at the API.
     */
    public function tradeIfReady(string $quoteId, string $narration, array $meta = []): array
    {
        $quote = $this->findQuote($quoteId)['data'] ?? [];
        $status = (string) ($quote['status'] ?? 'unknown');

        if ($status !== self::QUOTE_READY) {
            throw new InvalidArgument("Quote {$quoteId} is {$status}, not READY. ".($quote['complete_message'] ?? 'Request a new quote and try again.'));
        }

        return $this->trade($quoteId, $narration, $meta);
    }
}
