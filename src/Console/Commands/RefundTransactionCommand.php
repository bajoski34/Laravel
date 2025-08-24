<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console\Commands;

use Flutterwave\Payments\Facades\Flutterwave;
use Illuminate\Console\Command;

class RefundTransactionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flutterwave:refund
                            {transaction-id : The transaction ID to refund}
                            {--amount= : Partial refund amount (leave empty for full refund)}
                            {--verify : Verify transaction before refunding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refund a Flutterwave transaction';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $transactionId = $this->argument('transaction-id');
            $amount = $this->option('amount');
            $verify = $this->option('verify');

            $transactions = Flutterwave::use('transactions');

            // Verify transaction first if requested
            if ($verify) {
                $this->line('Verifying transaction...');
                
                $verification = $transactions->verify($transactionId);
                
                if (!isset($verification['status']) || $verification['status'] !== 'success') {
                    $this->error('Transaction verification failed or transaction not found');
                    return 1;
                }

                $transactionData = $verification['data'];
                
                $this->info('Transaction verified successfully!');
                $this->line('');
                $this->line('<comment>Transaction Details:</comment>');
                $this->line("ID: <info>{$transactionData['id']}</info>");
                $this->line("Reference: <info>{$transactionData['tx_ref']}</info>");
                $this->line("Amount: <info>{$transactionData['amount']} {$transactionData['currency']}</info>");
                $this->line("Status: <info>{$transactionData['status']}</info>");
                
                if (isset($transactionData['customer']['email'])) {
                    $this->line("Customer: <info>{$transactionData['customer']['email']}</info>");
                }
                
                $this->line('');

                // Check if transaction is eligible for refund
                if ($transactionData['status'] !== 'successful') {
                    $this->error('Transaction is not successful and cannot be refunded');
                    return 1;
                }

                // Confirm refund
                if (!$this->confirm('Do you want to proceed with the refund?')) {
                    $this->line('Refund cancelled');
                    return 0;
                }
            }

            // Validate amount if provided
            if ($amount !== null) {
                if (!is_numeric($amount) || $amount <= 0) {
                    $this->error('Refund amount must be a positive number');
                    return 1;
                }
                $this->line("Processing partial refund of {$amount}...");
            } else {
                $this->line('Processing full refund...');
            }

            // Process refund
            $refundResult = $transactions->refund($transactionId, $amount);

            if (isset($refundResult['status']) && $refundResult['status'] === 'success') {
                $this->info('✓ Refund processed successfully!');
                
                if (isset($refundResult['data'])) {
                    $refundData = $refundResult['data'];
                    $this->line('');
                    $this->line('<comment>Refund Details:</comment>');
                    
                    if (isset($refundData['flw_ref'])) {
                        $this->line("Flutterwave Reference: <info>{$refundData['flw_ref']}</info>");
                    }
                    
                    if (isset($refundData['amount_refunded'])) {
                        $this->line("Amount Refunded: <info>{$refundData['amount_refunded']}</info>");
                    }
                    
                    if (isset($refundData['status'])) {
                        $this->line("Status: <info>{$refundData['status']}</info>");
                    }
                    
                    if (isset($refundData['settlement_id'])) {
                        $this->line("Settlement ID: <info>{$refundData['settlement_id']}</info>");
                    }
                }
            } else {
                $this->error('✗ Refund failed');
                
                if (isset($refundResult['message'])) {
                    $this->line("Error: <comment>{$refundResult['message']}</comment>");
                }
                
                return 1;
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Error processing refund: ' . $e->getMessage());
            return 1;
        }
    }
}