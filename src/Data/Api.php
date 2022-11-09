<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Data;

class Api
{
    public const LATEST_VERSION = 'v3';
    public const BASE_URL = 'https://api.flutterwave.com/v3/';
    public const STANDARD_ENDPOINT = 'payments';
    public const TRANSACTIONS_ENDPOINT = 'transactions/';
    public const CHARGE_ENDPOINT = 'charges';
    public const SUBACCOUNTS_ENDPOINT = 'subaccounts/';
    public const TRANSFERS_ENDPOINT = 'transfers/';

    private array $versions = [
        'v3' => 'v3',
        'v2' => 'v2',
    ];

    private int $timeout = 30;
    private string $userAgent = 'Flutterwave-Laravel/1.0.0';

    public function getVersions(): array
    {
        return $this->versions;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }
}
