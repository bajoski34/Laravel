<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console\Commands;

use Flutterwave\Payments\Facades\Flutterwave;
use Illuminate\Console\Command;

class VerifyWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flutterwave:verify-webhook
                            {payload : The webhook payload (JSON string)}
                            {signature : The webhook signature}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify a Flutterwave webhook signature';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $payload = $this->argument('payload');
            $signature = $this->argument('signature');

            // Validate JSON payload
            $decodedPayload = json_decode($payload);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid JSON payload provided');
                return 1;
            }

            $webhook = Flutterwave::use('webhooks');
            
            // Verify signature
            $isValid = $webhook->verifySignature($payload, $signature);

            if ($isValid) {
                $this->info('✓ Webhook signature is valid!');
                
                // Parse and display webhook data if valid
                $this->line('');
                $this->line('<comment>Webhook Data:</comment>');
                
                if (isset($decodedPayload->data)) {
                    $data = $decodedPayload->data;
                    
                    if (isset($data->tx_ref)) {
                        $this->line("Transaction Reference: <info>{$data->tx_ref}</info>");
                    }
                    
                    if (isset($data->flw_ref)) {
                        $this->line("Flutterwave Reference: <info>{$data->flw_ref}</info>");
                    }
                    
                    if (isset($data->status)) {
                        $statusColor = $data->status === 'successful' ? 'info' : 'comment';
                        $this->line("Status: <{$statusColor}>{$data->status}</{$statusColor}>");
                    }
                    
                    if (isset($data->amount) && isset($data->currency)) {
                        $this->line("Amount: <info>{$data->amount} {$data->currency}</info>");
                    }
                    
                    if (isset($data->customer->email)) {
                        $this->line("Customer: <info>{$data->customer->email}</info>");
                    }
                    
                    if (isset($data->created_at)) {
                        $this->line("Created: <info>{$data->created_at}</info>");
                    }
                }
                
                return 0;
            } else {
                $this->error('✗ Webhook signature is invalid!');
                $this->line('');
                $this->line('<comment>This webhook may not be from Flutterwave or the payload may have been tampered with.</comment>');
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('Error verifying webhook: ' . $e->getMessage());
            return 1;
        }
    }
}