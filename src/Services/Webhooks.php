<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Data\Api;
use Illuminate\Support\Facades\Log;

final class Webhooks
{
    public const SECURE_HEADER = 'verif-hash';

    private string $secret_hash;

    /**
     * @return array
     */
    private string $hook;

    private Api $api;

    private \Psr\Log\LoggerInterface $logger;

    /**
     * Webhooks constructor.
     *
     * @param  array  $config
     */
    public function __construct(Api $api, array $config)
    {
        $this->api = $api;
        $this->secret_hash = $config['secret_hash'];
        $this->logger = Log::channel('flutterwave');
    }

    /**
     * @return array
     */
    public function getHook(): array
    {
        return json_decode($this->hook, true)['data'];
    }

    /**
     * Verify security header in request from Flutterwave
     */
    public function verifySignature(string $data, string $signature): bool
    {
        $this->logger->info('Flutterwave Webhook::Verifying signature from Flutterwave');
        if ($signature !== $this->secret_hash) {
            return false;
        }
        $this->logger->info('Flutterwave Webhook::Signature Verified');
        $this->hook = $data;

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

    /**
     * Verify hook structure sent by Flutterwave
     *
     * @param array $data
     */
    private function verifyHookStructure(array $data): bool
    {
        $this->logger->info("Flutterwave Webhook::Structure Verified for {$data['event']}");

        return isset($data['event']) && isset($data['data']) && $this->api::LATEST_VERSION === 'v3';
    }
}
