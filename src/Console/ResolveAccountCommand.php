<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Flutterwave\Payments\Exception\FlutterwaveException;
use Illuminate\Console\Command;

class ResolveAccountCommand extends Command
{
    protected $signature = 'flutterwave:resolve
        {account : Account number, mobile money number, or merchant ID when the merchant option is set}
        {bank? : Bank or mobile money provider code, see flutterwave:banks}
        {--country= : Two-letter country code, e.g. GH for Ghanaian banks and mobile money}
        {--merchant : Treat the account as a Flutterwave merchant ID}';

    protected $description = 'Look up the name on a bank account, mobile money number or Flutterwave merchant ID';

    public function handle(): int
    {
        $flutterwave = $this->laravel->make('flutterwave');
        $account = (string) $this->argument('account');
        $bank = $this->argument('bank');

        if (! $this->option('merchant') && empty($bank)) {
            $this->error('Pass a bank code, or --merchant to resolve a Flutterwave merchant ID.');

            return self::FAILURE;
        }

        try {
            $response = $this->option('merchant')
                ? $flutterwave->banks()->resolveMerchant($account)
                : $flutterwave->banks()->resolveAccount($account, (string) $bank, $this->option('country') ?: null);
        } catch (FlutterwaveException $e) {
            $this->error($e->getMessage());

            if (str_contains((string) $flutterwave->config('secret_key'), '_TEST')) {
                $this->line('Test keys can only resolve Flutterwave test accounts: https://developer.flutterwave.com/docs/testing#bank-accounts');
            }

            return self::FAILURE;
        }

        $data = $response['data'] ?? [];

        $this->table(['Field', 'Value'], [
            [$this->option('merchant') ? 'merchant id' : 'account number', $data['account_number'] ?? ''],
            ['account name', $data['account_name'] ?? ''],
        ]);

        return self::SUCCESS;
    }
}
