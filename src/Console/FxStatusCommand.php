<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Flutterwave\Payments\Console\Concerns\HandlesApiErrors;
use Flutterwave\Payments\Console\Concerns\ShowsFxQuotes;
use Illuminate\Console\Command;

class FxStatusCommand extends Command
{
    use HandlesApiErrors;
    use ShowsFxQuotes;

    protected $signature = 'flutterwave:fx-status
        {id : Quote id, or trade id with --trade}
        {--trade : Look up a trade instead of a quote}';

    protected $description = 'Check an FX quote or trade';

    public function handle(): int
    {
        $fx = $this->laravel->make('flutterwave')->fx();
        $id = (string) $this->argument('id');

        if (! $this->option('trade')) {
            $data = $this->attempt(fn () => $fx->findQuote($id))['data'] ?? null;

            if ($data === null) {
                return self::FAILURE;
            }

            $this->showQuote($data);

            return self::SUCCESS;
        }

        $trade = $this->attempt(fn () => $fx->findTrade($id))['data'] ?? null;

        if ($trade === null) {
            return self::FAILURE;
        }

        [$base, $target] = array_pad(explode('/', (string) ($trade['instrument'] ?? '')), 2, '');

        $this->table(['Field', 'Value'], array_values(array_filter([
            ['trade id', $trade['id'] ?? ''],
            ['status', $trade['status'] ?? ''],
            ['pair', $trade['instrument'] ?? ''],
            ['sold', $this->money($base, $trade['approved_quantity'] ?? $trade['quantity'] ?? null)],
            ['bought', $this->money($target, $trade['price'] ?? null)],
            ['recipient', trim(($trade['recipient']['name'] ?? '').' '.(isset($trade['recipient']['id']) ? "({$trade['recipient']['id']})" : ''))],
            ['narration', $trade['narration'] ?? ''],
            ['reference', $trade['reference'] ?? ''],
            empty($trade['response_message']) ? null : ['message', $trade['response_message']],
            ['created', $trade['created_at'] ?? ''],
        ])));

        return self::SUCCESS;
    }
}
