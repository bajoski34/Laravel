<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Exception\FlutterwaveException;
use Flutterwave\Payments\Exception\InvalidArgument;
use Illuminate\Support\Facades\Route;

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

    /** Pairs Flutterwave supports today, offered by the FX components. */
    public const PAIRS = [['NGN', 'USD'], ['GHS', 'USD'], ['USD', 'NGN']];

    /** Query parameters the FX routes redirect back with, so a page reload keeps showing the same quote or trade. */
    public const QUOTE_PARAM = 'fx_quote';

    public const TRADE_PARAM = 'fx_trade';

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

    /**
     * Everything the FX converter components need, as one array: e.g. an Inertia prop
     * or the :panel of <x-flutterwave-fx>. Pass the ids from the fx_quote and fx_trade
     * query parameters. API errors come back in `error` instead of being thrown.
     */
    public function panel(?string $quoteId = null, ?string $tradeId = null): array
    {
        $panel = [
            'quote' => null,
            'trade' => null,
            'error' => null,
            'pairs' => self::PAIRS,
            'urls' => Route::has('flutterwave.fx.quote')
                ? ['quote' => route('flutterwave.fx.quote'), 'trade' => route('flutterwave.fx.trade')]
                : null,
        ];

        try {
            if ($tradeId) {
                $panel['trade'] = $this->presentTrade($this->findTrade($tradeId)['data'] ?? []);
            }

            if ($quoteId) {
                $panel['quote'] = $this->presentQuote($this->findQuote($quoteId)['data'] ?? []);
            }
        } catch (FlutterwaveException $e) {
            $panel['error'] = $e->getMessage();
        }

        return $panel;
    }

    private function presentQuote(array $data): array
    {
        [$base, $target] = array_pad(explode('/', (string) ($data['instrument'] ?? '')), 2, null);
        $quote = $data['quote'] ?? null;
        // Quote expiry times are UTC (they end in Z), so this is safe to compare with time().
        $expiry = $quote ? (strtotime((string) ($quote['expiry'] ?? '')) ?: null) : null;
        $status = (string) ($data['status'] ?? '');

        return [
            'id' => $data['id'] ?? null,
            'reference' => $data['reference'] ?? null,
            'status' => $status,
            'base' => $base,
            'target' => $target,
            'quantity' => $data['quantity'] ?? null,
            'approved_quantity' => $quote['approved_quantity'] ?? null,
            'rate' => isset($quote['best_available_rate']) ? (string) $quote['best_available_rate'] : null,
            'total_value' => $quote['total_value'] ?? null,
            'expires_at' => $expiry ? date(DATE_ATOM, $expiry) : null,
            'expires_in' => $expiry ? max(0, $expiry - time()) : null,
            'message' => $data['complete_message'] ?? null,
            'pending' => in_array($status, ['NEW', 'PROCESSING'], true),
            'tradable' => $status === self::QUOTE_READY && $expiry !== null && $expiry > time(),
        ];
    }

    private function presentTrade(array $data): array
    {
        [$base, $target] = array_pad(explode('/', (string) ($data['instrument'] ?? '')), 2, null);
        $status = (string) ($data['status'] ?? '');

        return [
            'id' => $data['id'] ?? null,
            'status' => $status,
            'base' => $base,
            'target' => $target,
            'sold' => $data['approved_quantity'] ?? $data['quantity'] ?? null,
            'bought' => $data['price'] ?? null,
            'recipient' => $data['recipient']['name'] ?? null,
            'narration' => $data['narration'] ?? null,
            'reference' => $data['reference'] ?? null,
            'message' => $data['response_message'] ?? null,
            'created_at' => $data['created_at'] ?? null,
            'pending' => ! in_array($status, [self::TRADE_SETTLED, 'FAILED'], true),
        ];
    }
}
