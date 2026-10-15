<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Flutterwave\Payments\Console\Concerns\HandlesApiErrors;
use Illuminate\Console\Command;

class ListChargebacksCommand extends Command
{
    use HandlesApiErrors;

    protected $signature = 'flutterwave:chargebacks
        {--status= : initiated, accepted, declined, won or lost}
        {--from= : Raised on or after this date, YYYY-MM-DD}
        {--to= : Raised on or before this date, YYYY-MM-DD}
        {--currency= : Three-letter currency code, e.g. NGN}
        {--ref= : Show one chargeback and its history, by the transaction\'s flw_ref}
        {--page= : Page of results}';

    protected $description = 'List chargebacks raised against your account';

    public function handle(): int
    {
        $chargebacks = $this->laravel->make('flutterwave')->chargebacks();
        $ref = $this->option('ref');

        $response = $this->attempt(fn () => $ref
            ? $chargebacks->find((string) $ref)
            : $chargebacks->all(array_filter([
                'status' => $this->option('status') ? strtolower((string) $this->option('status')) : null,
                'from' => $this->option('from'),
                'to' => $this->option('to'),
                'currency' => $this->option('currency') ? strtoupper((string) $this->option('currency')) : null,
                'page' => $this->option('page'),
            ])));

        if ($response === null) {
            return self::FAILURE;
        }

        $items = $response['data'] ?? [];

        if ($items === []) {
            $this->info($ref ? "No chargeback found for {$ref}." : 'No chargebacks match.');

            return self::SUCCESS;
        }

        if ($ref) {
            $this->showOne($items[0]);

            return self::SUCCESS;
        }

        $this->table(['ID', 'Amount', 'Status', 'Stage', 'Respond by', 'Transaction', 'flw_ref'], array_map(static fn (array $chargeback) => [
            $chargeback['id'] ?? '',
            $chargeback['amount'] ?? '',
            $chargeback['status'] ?? '',
            $chargeback['stage'] ?? '',
            $chargeback['due_date'] ?? '',
            $chargeback['tx_ref'] ?? '',
            $chargeback['flw_ref'] ?? '',
        ], $items));

        $page = $response['meta']['page_info'] ?? [];
        if (isset($page['current_page'], $page['total_pages'])) {
            $this->line("Page {$page['current_page']} of {$page['total_pages']}, {$page['total']} in total.");
        }

        $open = count(array_filter($items, static fn (array $chargeback) => ($chargeback['status'] ?? null) === 'initiated'));
        if ($open > 0) {
            $this->warn("{$open} ".($open === 1 ? 'chargeback needs' : 'chargebacks need').' a response before the due date. Use flutterwave:accept-chargeback or flutterwave:decline-chargeback.');
        }

        return self::SUCCESS;
    }

    private function showOne(array $chargeback): void
    {
        $this->table(['Field', 'Value'], [
            ['id', $chargeback['id'] ?? ''],
            ['amount', $chargeback['amount'] ?? ''],
            ['status', $chargeback['status'] ?? ''],
            ['stage', $chargeback['stage'] ?? ''],
            ['comment', $chargeback['comment'] ?? ''],
            ['respond by', $chargeback['due_date'] ?? ''],
            ['transaction', trim(($chargeback['transaction_id'] ?? '').' '.($chargeback['tx_ref'] ?? ''))],
            ['flw_ref', $chargeback['flw_ref'] ?? ''],
            ['proof', $chargeback['meta']['uploaded_proof'] ?? 'none'],
        ]);

        $history = $chargeback['meta']['history'] ?? [];
        if ($history !== []) {
            $this->table(['Date', 'Action', 'What happened'], array_map(static fn (array $entry) => [
                $entry['date'] ?? '',
                $entry['action'] ?? '',
                trim(($entry['description'] ?? '').(isset($entry['comment']) ? ": {$entry['comment']}" : '')),
            ], $history));
        }
    }
}
