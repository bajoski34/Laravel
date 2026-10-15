<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Flutterwave\Payments\Console\Concerns\HandlesApiErrors;
use Flutterwave\Payments\Console\Concerns\ShowsFxQuotes;
use Flutterwave\Payments\Services\Fx;
use Illuminate\Console\Command;

class FxTradeCommand extends Command
{
    use HandlesApiErrors;
    use ShowsFxQuotes;

    protected $signature = 'flutterwave:fx-trade
        {quote : Quote id from flutterwave:fx-quote}
        {--narration= : A note for your records, e.g. Supplier payment}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Trade a ready FX quote at its quoted rate';

    public function handle(): int
    {
        $fx = $this->laravel->make('flutterwave')->fx();
        $quoteId = (string) $this->argument('quote');

        $data = $this->attempt(fn () => $fx->findQuote($quoteId))['data'] ?? null;

        if ($data === null) {
            return self::FAILURE;
        }

        $this->showQuote($data);

        if (($data['status'] ?? null) !== Fx::QUOTE_READY) {
            $this->error("This quote is {$data['status']}, so it can't be traded. Request a new one with flutterwave:fx-quote.");

            return self::FAILURE;
        }

        [$base, $target] = array_pad(explode('/', (string) $data['instrument']), 2, '');
        $narration = (string) ($this->option('narration') ?? $this->ask('Narration for your records'));

        if (! $this->confirmChange('Sell '.$this->money($base, $data['quote']['approved_quantity'] ?? null).' for '.$this->money($target, $data['quote']['total_value'] ?? null).'? Trades can\'t be undone.')) {
            return self::FAILURE;
        }

        $trade = $this->attempt(fn () => $fx->trade($quoteId, $narration))['data'] ?? null;

        if ($trade === null) {
            return self::FAILURE;
        }

        $this->info("Trade {$trade['id']} is {$trade['status']}.");
        $this->line("Follow it with: php artisan flutterwave:fx-status {$trade['id']} --trade");

        return self::SUCCESS;
    }
}
