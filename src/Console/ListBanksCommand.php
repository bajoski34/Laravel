<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Illuminate\Console\Command;

class ListBanksCommand extends Command
{
    protected $signature = 'flutterwave:banks
        {country=NG : Two-letter country code (NG, GH, KE, UG, ZA, TZ...)}
        {--search= : Only show banks whose name contains this text}';

    protected $description = 'List bank codes for transfers and account resolution';

    public function handle(): int
    {
        $banks = $this->laravel->make('flutterwave')->banks()->all((string) $this->argument('country'))['data'] ?? [];
        $search = $this->option('search');

        if ($search) {
            $banks = array_filter($banks, static fn (array $bank) => stripos($bank['name'] ?? '', (string) $search) !== false);
        }

        $this->table(['Code', 'Name'], array_map(static fn (array $bank) => [$bank['code'] ?? '', $bank['name'] ?? ''], $banks));

        return self::SUCCESS;
    }
}
