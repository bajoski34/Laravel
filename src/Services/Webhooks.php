<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Data\Api;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

class Webhooks
{
    /** Legacy header: the raw secret hash set on your dashboard. */
    public const SECURE_HEADER = 'verif-hash';

    /** Newer header: base64 HMAC-SHA256 of the raw body, keyed with the secret hash. */
    public const SIGNATURE_HEADER = 'flutterwave-signature';

    private string $secret_hash;

    private string $hook = '';

    private Api $api;

    private LoggerInterface $logger;

    public function __construct(Api $api, array $config)
    {
        $this->api = $api;
        $this->secret_hash = (string) ($config['secret_hash'] ?? '');
        $this->logger = Log::channel('flutterwave');
    }

    public function getHook(): array
    {
        $payload = json_decode($this->hook, true);

        return is_array($payload) ? ($payload['data'] ?? $payload) : [];
    }

    /**
     * Verify the `verif-hash` header sent by Flutterwave.
     */
    public function verifySignature(string $data, ?string $signature): bool
    {
        return $this->isValid($data, $signature, null);
    }

    /**
     * Verify a webhook using whichever signature header Flutterwave sent.
     */
    public function isValid(string $body, ?string $verifHash, ?string $hmacSignature = null): bool
    {
        if ($this->secret_hash === '') {
            $this->logger->warning('Flutterwave Webhook::FLW_SECRET_HASH is not set, rejecting webhook. Set it to the secret hash on your Flutterwave dashboard.');

            return false;
        }

        $valid = false;

        if (! empty($hmacSignature)) {
            $expected = base64_encode(hash_hmac('sha256', $body, $this->secret_hash, true));
            $valid = hash_equals($expected, $hmacSignature);
        } elseif (! empty($verifHash)) {
            $valid = hash_equals($this->secret_hash, $verifHash);
        }

        if (! $valid) {
            $this->logger->warning('Flutterwave Webhook::Invalid signature');

            return false;
        }

        $this->hook = $body;

        return true;
    }

    public function eventAfterHook(callable $func): void
    {
        call_user_func($func, $this->hook);
    }

    public function eventBeforeHook(callable $func): void
    {
        call_user_func($func, $this->hook);
    }
}
