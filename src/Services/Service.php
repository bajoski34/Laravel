<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Data\Api;
use Flutterwave\Payments\Support\ApiClient;

/**
 * Base class for Flutterwave API services.
 *
 * Every method returns the decoded Flutterwave response
 * (`status`, `message`, `data` and, for lists, `meta`) and throws
 * FlutterwaveException when the API returns an error.
 */
abstract class Service
{
    protected Api $api;

    protected array $config;

    protected ApiClient $client;

    public function __construct(Api $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
        $this->client = ApiClient::fromConfig($config);
    }

    protected function filter(array $values): array
    {
        return array_filter($values, static fn ($value) => $value !== null);
    }
}
