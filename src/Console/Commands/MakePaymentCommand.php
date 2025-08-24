<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console\Commands;

use Flutterwave\Payments\Data\Currency;
use Flutterwave\Payments\Facades\Flutterwave;
use Illuminate\Console\Command;

class MakePaymentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flutterwave:payment
                            {amount : The payment amount}
                            {email : Customer email}
                            {--currency= : Payment currency (default: NGN)}
                            {--redirect-url= : Custom redirect URL}
                            {--tx-ref= : Custom transaction reference}
                            {--customer-name= : Customer name}
                            {--customer-phone= : Customer phone}
                            {--type=standard : Payment type (standard, inline)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a Flutterwave payment link';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $amount = $this->argument('amount');
            $email = $this->argument('email');
            $currency = $this->option('currency') ?? Currency::NGN;
            $type = $this->option('type');

            // Validate amount
            if (!is_numeric($amount) || $amount <= 0) {
                $this->error('Amount must be a positive number');
                return 1;
            }

            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error('Invalid email address');
                return 1;
            }

            // Prepare payment data
            $txRef = $this->option('tx-ref') ?? Flutterwave::generateTransactionReference();
            
            $payload = [
                'tx_ref' => $txRef,
                'amount' => (float) $amount,
                'currency' => $currency,
                'customer' => [
                    'email' => $email,
                ],
            ];

            // Add optional customer details
            if ($this->option('customer-name')) {
                $payload['customer']['name'] = $this->option('customer-name');
            }

            if ($this->option('customer-phone')) {
                $payload['customer']['phonenumber'] = $this->option('customer-phone');
            }

            // Add custom redirect URL if provided
            if ($this->option('redirect-url')) {
                $payload['redirect_url'] = $this->option('redirect-url');
            }

            // Generate payment link
            $paymentLink = Flutterwave::render($type, $payload);

            $this->info('Payment link generated successfully!');
            $this->line('');
            $this->line("Transaction Reference: <comment>{$txRef}</comment>");
            $this->line("Amount: <comment>{$amount} {$currency}</comment>");
            $this->line("Customer: <comment>{$email}</comment>");
            $this->line('');
            $this->line("Payment Link: <info>{$paymentLink}</info>");

            return 0;
        } catch (\Exception $e) {
            $this->error('Error generating payment link: ' . $e->getMessage());
            return 1;
        }
    }
}