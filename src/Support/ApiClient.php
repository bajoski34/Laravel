<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Support;

use Flutterwave\Payments\Data\Api;
use Flutterwave\Payments\Exception\FlutterwaveException;
use Flutterwave\Payments\Exception\InvalidArgument;
use Flutterwave\Payments\Exception\NetworkConnection;
use Flutterwave\Payments\Flutterwave;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around Laravel's HTTP client for the Flutterwave v3 API.
 */
class ApiClient
{
    private string $secretKey;

    private string $baseUrl;

    private int $timeout;

    private int $retries;

    public function __construct(?string $secretKey, int $timeout = 60, int $retries = 2, ?string $baseUrl = null)
    {
        $this->secretKey = (string) $secretKey;
        $this->timeout = $timeout;
        $this->retries = max(0, $retries);
        $this->baseUrl = rtrim($baseUrl ?? Api::BASE_URL.'/'.Api::LATEST_VERSION, '/');
    }

    public static function fromConfig(array $config): self
    {
        return new self(
            $config['secret_key'] ?? null,
            (int) ($config['timeout'] ?? 60),
            (int) ($config['retries'] ?? 2),
        );
    }

    public function get(string $uri, array $query = [], bool $throw = true): array
    {
        return $this->send('GET', $uri, $query, $throw);
    }

    public function post(string $uri, array $data = [], bool $throw = true): array
    {
        return $this->send('POST', $uri, $data, $throw);
    }

    public function put(string $uri, array $data = [], bool $throw = true): array
    {
        return $this->send('PUT', $uri, $data, $throw);
    }

    public function delete(string $uri, array $data = [], bool $throw = true): array
    {
        return $this->send('DELETE', $uri, $data, $throw);
    }

    /**
     * @throws FlutterwaveException when the API responds with an error and $throw is true
     * @throws NetworkConnection when Flutterwave cannot be reached
     */
    public function send(string $method, string $uri, array $data = [], bool $throw = true): array
    {
        if ($this->secretKey === '') {
            throw new InvalidArgument('The Flutterwave secret key is missing. Add FLW_SECRET_KEY to your .env file.');
        }

        $url = $this->baseUrl.'/'.ltrim($uri, '/');

        // Only GET requests are retried: replaying a POST (e.g. a transfer)
        // after a dropped connection could move money twice.
        $attempts = $method === 'GET' ? $this->retries + 1 : 1;

        for ($attempt = 1; ; $attempt++) {
            try {
                $response = Http::withToken($this->secretKey)
                    ->acceptJson()
                    ->timeout($this->timeout)
                    ->withUserAgent('Flutterwave-Laravel/'.Flutterwave::VERSION)
                    ->send($method, $url, $method === 'GET' ? ['query' => $data] : ['json' => $data]);
                break;
            } catch (ConnectionException $e) {
                if ($attempt >= $attempts) {
                    Log::channel('flutterwave')->error("Flutterwave::Unable to reach {$method} {$url}: {$e->getMessage()}");

                    throw new NetworkConnection('Unable to connect to the Flutterwave API. Please check your network connection.', 0, [], $e);
                }
                usleep(100000 * $attempt);
            }
        }

        $body = $response->json();
        $body = is_array($body) ? $body : [];

        if ($response->failed()) {
            Log::channel('flutterwave')->warning("Flutterwave::{$method} {$uri} failed with HTTP {$response->status()}", $body);

            if ($throw) {
                throw FlutterwaveException::fromResponse($response->status(), $body);
            }
        }

        return $body;
    }
}
