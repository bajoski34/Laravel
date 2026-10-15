<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Illuminate\Console\Command;

class CardBinCommand extends Command
{
    protected $signature = 'flutterwave:bin
        {bin : First six digits of the card}';

    protected $description = 'Look up the issuer, type and country of a card';

    public function handle(): int
    {
        $data = $this->laravel->make('flutterwave')->cards()->bin((string) $this->argument('bin'))['data'] ?? [];

        $this->table(['Field', 'Value'], [
            ['bin', $data['bin'] ?? ''],
            ['issuer', $data['issuer_info'] ?? ''],
            ['card type', $data['card_type'] ?? ''],
            ['country', $data['issuing_country'] ?? ''],
        ]);

        return self::SUCCESS;
    }
}
