<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Illuminate\Console\Command;

class VerifyTransactionCommand extends Command
{
    protected $signature = 'flutterwave:verify
        {reference : Transaction id, or tx_ref when --ref is passed}
        {--ref : Treat the argument as a tx_ref}';

    protected $description = 'Look up the status of a Flutterwave transaction';

    public function handle(): int
    {
        $flutterwave = $this->laravel->make('flutterwave');
        $reference = (string) $this->argument('reference');

        $response = $this->option('ref')
            ? $flutterwave->verifyTransactionReference($reference)
            : $flutterwave->verifyTransaction($reference);

        if (($response['status'] ?? null) !== 'success') {
            $this->error($response['message'] ?? 'Transaction could not be verified.');

            return self::FAILURE;
        }

        $data = $response['data'];
        $this->table(['Field', 'Value'], [
            ['id', $data['id'] ?? ''],
            ['tx_ref', $data['tx_ref'] ?? ''],
            ['status', $data['status'] ?? ''],
            ['amount', ($data['currency'] ?? '').' '.($data['amount'] ?? '')],
            ['charged', ($data['currency'] ?? '').' '.($data['charged_amount'] ?? '')],
            ['payment type', $data['payment_type'] ?? ''],
            ['customer', $data['customer']['email'] ?? ''],
            ['created at', $data['created_at'] ?? ''],
        ]);

        return self::SUCCESS;
    }
}
