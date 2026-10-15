<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console\Concerns;

trait ShowsFxQuotes
{
    protected function showQuote(array $data): void
    {
        [$base, $target] = array_pad(explode('/', (string) ($data['instrument'] ?? '')), 2, '');
        $quote = $data['quote'] ?? null;

        $this->table(['Field', 'Value'], array_values(array_filter([
            ['quote id', $data['id'] ?? ''],
            ['status', $data['status'] ?? ''],
            ['pair', $data['instrument'] ?? ''],
            ['requested', $this->money($base, $data['quantity'] ?? null)],
            $quote ? ['approved', $this->money($base, $quote['approved_quantity'] ?? null)] : null,
            $quote ? ['rate', (string) ($quote['best_available_rate'] ?? '')] : null,
            $quote ? ['you get', $this->money($target, $quote['total_value'] ?? null)] : null,
            $quote ? ['expires', (string) ($quote['expiry'] ?? '')] : null,
            ['reference', $data['reference'] ?? ''],
            empty($data['complete_message']) ? null : ['message', $data['complete_message']],
        ])));
    }

    protected function money(string $currency, mixed $amount): string
    {
        return is_numeric($amount) ? trim($currency.' '.number_format((float) $amount, 2)) : '';
    }
}
