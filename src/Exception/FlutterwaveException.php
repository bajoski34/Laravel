<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Exception;

use RuntimeException;
use Throwable;

/**
 * Thrown when the Flutterwave API rejects a request (4xx/5xx).
 * Catch this one class to handle every API failure raised by the package.
 */
class FlutterwaveException extends RuntimeException
{
    private int $statusCode;

    private array $response;

    public function __construct(string $message = '', int $statusCode = 0, array $response = [], ?Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->response = $response;
    }

    public static function fromResponse(int $statusCode, array $response): self
    {
        $message = $response['message'] ?? "Flutterwave API request failed with HTTP status {$statusCode}.";

        return new self((string) $message, $statusCode, $response);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * The decoded JSON body returned by Flutterwave.
     */
    public function getResponse(): array
    {
        return $this->response;
    }
}
