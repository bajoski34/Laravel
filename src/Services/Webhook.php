<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Data\Api;

final class Webhooks
{
    private string $secret_hash;
    /**
     * @return array
     */
    private array $hook;
    private Api $api;

    /**
     * Webhooks constructor.
     *
     * @param array $config
     */
    public function __construct(Api $api, array $config)
    {
        $this->api = $api;
        $this->secret_hash = $config['secret_hash'];
    }

    /**
     * @param array $data
     */
    public function verifySignature(array $data, string $signature): bool
    {
        if ($signature !== $this->secret_hash) {
            return false;
        }
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
     * @param array $data
     */
    private function verifyHookStructure(array $data): bool
    {
        return isset($data['event']) && isset($data['data']) && $this->api::LATEST_VERSION === 'v3';
    }
}
