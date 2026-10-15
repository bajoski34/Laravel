<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Illuminate\Console\Command;

class BalanceCommand extends Command
{
    protected $signature = 'flutterwave:balance
        {--currency= : Only show this currency (NGN, GHS, USD, EUR...)}';

    protected $description = 'Show your Flutterwave wallet balances';

    public function handle(): int
    {
        $balances = $this->laravel->make('flutterwave')->balances();
        $currency = $this->option('currency');

        $data = $currency ? [$balances->currency((string) $currency)['data'] ?? []] : $balances->all()['data'] ?? [];

        $this->table(['Currency', 'Available', 'Ledger'], array_map(static fn (array $balance) => [
            $balance['currency'] ?? '',
            $balance['available_balance'] ?? '',
            $balance['ledger_balance'] ?? '',
        ], $data));

        return self::SUCCESS;
    }
}
