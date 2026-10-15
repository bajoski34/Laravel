<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class PingWebhookCommand extends Command
{
    protected $signature = 'flutterwave:ping-webhook
        {id : Transaction id, or transfer id with --event=transfer}
        {url? : Webhook URL, defaults to this app\'s webhook route}
        {--event=charge : charge or transfer}
        {--hash= : Secret hash to sign with, defaults to FLW_SECRET_HASH}';

    protected $description = 'Send a signed webhook for a real transaction to a local URL, without exposing it to the internet';

    public function handle(): int
    {
        $flutterwave = $this->laravel->make('flutterwave');
        $id = (string) $this->argument('id');

        $payload = match ($this->option('event')) {
            'charge', 'card' => ['event' => 'charge.completed', 'event.type' => 'CARD_TRANSACTION', 'data' => $flutterwave->verifyTransaction($id)['data'] ?? null],
            'transfer' => ['event' => 'transfer.completed', 'event.type' => 'Transfer', 'data' => $flutterwave->transfers()->find($id)['data'] ?? null],
            default => null,
        };

        if ($payload === null) {
            $this->error('The event option must be charge or transfer.');

            return self::FAILURE;
        }

        if (empty($payload['data'])) {
            $this->error("No {$this->option('event')} with id {$id} was found.");

            return self::FAILURE;
        }

        $url = (string) ($this->argument('url') ?: url((string) config('flutterwave.webhook.path', 'flutterwave/webhook')));
        $hash = (string) ($this->option('hash') ?? $flutterwave->config('secret_hash'));
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        if ($hash === '') {
            $this->warn('No secret hash set, so the webhook is unsigned. Set FLW_SECRET_HASH or pass --hash.');
        }

        try {
            $response = Http::withHeaders(array_filter([
                'verif-hash' => $hash,
                'flutterwave-signature' => $hash === '' ? '' : base64_encode(hash_hmac('sha256', $body, $hash, true)),
            ]))->withBody($body, 'application/json')->post($url);
        } catch (ConnectionException $e) {
            $this->error("Could not reach {$url}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->line("{$payload['event']} sent to {$url}");
        $this->line("HTTP {$response->status()} ".mb_strimwidth($response->body(), 0, 200, '...'));

        return $response->successful() ? self::SUCCESS : self::FAILURE;
    }
}
