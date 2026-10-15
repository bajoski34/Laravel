<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Flutterwave\Payments\Console\Concerns\HandlesApiErrors;
use Illuminate\Console\Command;

class ListBillsCommand extends Command
{
    use HandlesApiErrors;

    protected $signature = 'flutterwave:bills
        {category? : Category code to list its billers, e.g. AIRTIME or UTILITYBILLS}
        {--biller= : Biller code to list what it sells, e.g. BIL119}
        {--country=NG : Two-letter country code}';

    protected $description = 'Find the category, biller and item codes for paying bills';

    public function handle(): int
    {
        $bills = $this->laravel->make('flutterwave')->bills();
        $country = (string) $this->option('country');

        if ($biller = $this->option('biller')) {
            $items = $this->attempt(fn () => $bills->items((string) $biller))['data'] ?? null;

            if ($items === null) {
                return self::FAILURE;
            }

            $this->table(['Item code', 'Name', 'Amount', 'Fee', 'Customer number'], array_map(static fn (array $item) => [
                $item['item_code'] ?? '',
                $item['short_name'] ?? $item['name'] ?? '',
                ($item['amount'] ?? 0) ? number_format((float) $item['amount'], 2) : 'any',
                $item['fee'] ?? 0,
                $item['label_name'] ?? '',
            ], $items));
            $this->line("Pay with Flutterwave::bills()->payValidated('{$biller}', \$itemCode, \$customerNumber, \$amount).");

            return self::SUCCESS;
        }

        if ($category = $this->argument('category')) {
            $billers = $this->attempt(fn () => $bills->billers((string) $category, $country))['data'] ?? null;

            if ($billers === null) {
                return self::FAILURE;
            }

            $this->table(['Biller code', 'Name'], array_map(static fn (array $biller) => [$biller['biller_code'] ?? '', $biller['name'] ?? ''], $billers));
            $this->line('See what a biller sells with: php artisan flutterwave:bills --biller=<code>');

            return self::SUCCESS;
        }

        $categories = $this->attempt(fn () => $bills->categories($country))['data'] ?? null;

        if ($categories === null) {
            return self::FAILURE;
        }

        $this->table(['Category code', 'Name'], array_map(static fn (array $category) => [$category['code'] ?? '', $category['name'] ?? ''], $categories));
        $this->line('List a category\'s billers with: php artisan flutterwave:bills <code>');

        return self::SUCCESS;
    }
}
