<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Flutterwave\Payments\Console\Concerns\HandlesApiErrors;
use Flutterwave\Payments\Console\Concerns\ShowsFxQuotes;
use Flutterwave\Payments\Services\Fx;
use Illuminate\Console\Command;

class FxQuoteCommand extends Command
{
    use HandlesApiErrors;
    use ShowsFxQuotes;

    protected $signature = 'flutterwave:fx-quote
        {base : Currency you sell, e.g. NGN}
        {target : Currency you buy, e.g. USD}
        {quantity : Amount of the base currency to sell}
        {--reference= : Your own reference for the quote}
        {--no-wait : Return straight away instead of waiting for the rate}
        {--timeout=60 : Seconds to wait for the rate}
        {--poll=2 : Seconds between checks while waiting}';

    protected $description = 'Get an FX rate you can trade within 5 minutes';

    private const WAITING = ['NEW', 'PROCESSING'];

    public function handle(): int
    {
        $fx = $this->laravel->make('flutterwave')->fx();

        $response = $this->attempt(fn () => $fx->quote(
            (string) $this->argument('base'),
            (string) $this->argument('target'),
            (string) $this->argument('quantity'),
            $this->option('reference') ?: null,
        ));

        if ($response === null) {
            return self::FAILURE;
        }

        $data = $response['data'] ?? [];

        if (! $this->option('no-wait')) {
            $deadline = time() + (int) $this->option('timeout');

            while (in_array($data['status'] ?? null, self::WAITING, true) && time() <= $deadline) {
                sleep(max(0, (int) $this->option('poll')));
                $checked = $this->attempt(fn () => $fx->findQuote((string) $data['id']));

                if ($checked === null) {
                    $this->line("Check it later with: php artisan flutterwave:fx-status {$data['id']}");

                    return self::FAILURE;
                }

                $data = $checked['data'] ?? $data;
            }
        }

        $this->showQuote($data);

        return match ($data['status'] ?? null) {
            Fx::QUOTE_READY => $this->ready($data),
            'NEW', 'PROCESSING' => $this->pending($data),
            default => self::FAILURE,
        };
    }

    private function ready(array $data): int
    {
        $this->info('Trade it within 5 minutes:');
        $this->line("  php artisan flutterwave:fx-trade {$data['id']} --narration=\"...\"");

        return self::SUCCESS;
    }

    private function pending(array $data): int
    {
        $this->line('The rate isn\'t ready yet. Check again with:');
        $this->line("  php artisan flutterwave:fx-status {$data['id']}");

        return self::SUCCESS;
    }
}
