<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Flutterwave\Payments\Console\Concerns\HandlesApiErrors;
use Flutterwave\Payments\Support\Audit;
use Illuminate\Console\Command;

class AcceptChargebackCommand extends Command
{
    use HandlesApiErrors;

    protected $signature = 'flutterwave:accept-chargeback
        {id : Chargeback id, from flutterwave:chargebacks}
        {--comment= : Why you are accepting it}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Accept a chargeback and refund the customer';

    public function handle(): int
    {
        $id = (string) $this->argument('id');
        $comment = (string) ($this->option('comment') ?? $this->ask('Why are you accepting this chargeback?'));

        if (! $this->confirmChange("Accept chargeback {$id}? The customer is refunded and this can't be undone.")) {
            return self::FAILURE;
        }

        $response = $this->attempt(fn () => $this->laravel->make('flutterwave')->chargebacks()->accept($id, $comment));

        Audit::record('chargeback.accept', ['chargeback_id' => $id], $response !== null);

        if ($response === null) {
            return self::FAILURE;
        }

        $this->info($response['message'] ?? 'Chargeback accepted.');

        return self::SUCCESS;
    }
}
