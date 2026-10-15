<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Illuminate\Console\Command;

class ResendWebhookCommand extends Command
{
    protected $signature = 'flutterwave:resend-webhook
        {transaction : Transaction id (data.id)}';

    protected $description = 'Ask Flutterwave to resend a failed webhook to your dashboard webhook URL';

    public function handle(): int
    {
        $response = $this->laravel->make('flutterwave')->transactions()->resendFailedHooks((string) $this->argument('transaction'));

        if (($response['status'] ?? null) !== 'success') {
            $this->error($response['message'] ?? 'The webhook could not be resent.');

            return self::FAILURE;
        }

        $this->info($response['message'] ?? 'Webhook resent.');

        return self::SUCCESS;
    }
}
