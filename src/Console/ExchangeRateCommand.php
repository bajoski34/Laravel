<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Illuminate\Console\Command;

class ExchangeRateCommand extends Command
{
    protected $signature = 'flutterwave:rate
        {--amount=1 : Amount to convert, in the destination currency}
        {--from=USD : Currency you pay from}
        {--to=NGN : Currency the recipient gets}';

    protected $description = 'Show the exchange rate Flutterwave uses for transfers';

    public function handle(): int
    {
        $data = $this->laravel->make('flutterwave')->transfers()->rates(
            (float) $this->option('amount'),
            strtoupper((string) $this->option('to')),
            strtoupper((string) $this->option('from')),
        )['data'] ?? [];

        $this->table(['Field', 'Value'], [
            ['rate', $data['rate'] ?? ''],
            ['you pay', ($data['source']['currency'] ?? '').' '.($data['source']['amount'] ?? '')],
            ['they get', ($data['destination']['currency'] ?? '').' '.($data['destination']['amount'] ?? '')],
        ]);

        return self::SUCCESS;
    }
}
