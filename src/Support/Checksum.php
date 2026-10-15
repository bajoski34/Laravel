<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Support;

use Flutterwave\Payments\Exception\InvalidArgument;

/**
 * The payload_hash Flutterwave uses to reject a checkout whose amount, currency, email
 * or reference was changed in the browser:
 *
 *     sha256(amount . currency . customer_email . tx_ref . sha256(secret_key))
 *
 * Turn it on for every checkout with FLW_CHECKSUM=true, or call make() for a custom one.
 *
 * @see https://developer.flutterwave.com/docs/checksum
 */
final class Checksum
{
    /**
     * @param  array  $payload  the checkout exactly as it will be sent: amount, currency, tx_ref and customer.email (or email)
     */
    public static function make(array $payload, string $secretKey): string
    {
        $email = $payload['customer']['email'] ?? $payload['email'] ?? null;

        foreach (['amount' => $payload['amount'] ?? null, 'currency' => $payload['currency'] ?? null, 'customer email' => $email, 'tx_ref' => $payload['tx_ref'] ?? null] as $field => $value) {
            if ($value === null || $value === '') {
                throw new InvalidArgument("The checkout {$field} is needed to build its payload_hash.");
            }
        }

        if ($secretKey === '') {
            throw new InvalidArgument('The Flutterwave secret key is missing. Add FLW_SECRET_KEY to your .env file.');
        }

        return hash('sha256', self::amount($payload['amount']).$payload['currency'].$email.$payload['tx_ref'].hash('sha256', $secretKey));
    }

    /**
     * The amount as Flutterwave's own hash sees it. Strings are sent as-is. Numbers are written
     * the way JavaScript writes them once the JSON is parsed: 5000 and 5000.0 are both "5000",
     * 100.5 is "100.5". (PHP's json_encode would write 5000.0 as "5000.0".)
     */
    private static function amount(mixed $amount): string
    {
        if (is_string($amount)) {
            return $amount;
        }

        if (is_float($amount) && floor($amount) === $amount && abs($amount) < 1e15) {
            return (string) (int) $amount;
        }

        return (string) json_encode($amount);
    }
}
