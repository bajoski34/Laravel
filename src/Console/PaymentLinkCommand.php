<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class PaymentLinkCommand extends Command
{
    protected $signature = 'flutterwave:pay
        {email : Customer email}
        {amount : Amount to charge}
        {--currency= : Defaults to the currency in config/flutterwave.php}
        {--open : Open the payment link in your browser}';

    protected $description = 'Create a hosted checkout link for a quick test payment';

    public function handle(): int
    {
        $link = $this->laravel->make('flutterwave')->checkout(array_filter([
            'email' => (string) $this->argument('email'),
            'amount' => $this->argument('amount'),
            'currency' => $this->option('currency') ? strtoupper((string) $this->option('currency')) : null,
        ]));

        $this->line($link);

        if ($this->option('open')) {
            $opener = match (PHP_OS_FAMILY) {
                'Darwin' => ['open'],
                'Windows' => ['cmd', '/c', 'start', ''],
                default => ['xdg-open'],
            };

            (new Process([...$opener, $link]))->run();
        }

        return self::SUCCESS;
    }
}
