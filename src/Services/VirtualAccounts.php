<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Exception\InvalidArgument;
use Illuminate\Support\Facades\Cache;

/**
 * Dedicated bank account numbers your customers can pay into.
 *
 * @see https://developer.flutterwave.com/docs/collecting-payments/virtual-account-numbers
 */
class VirtualAccounts extends Service
{
    /** Longest a dynamic account can stay open, in seconds: two months for NGN, 48 hours for GHS. */
    public const MAX_EXPIRY = ['NGN' => 5270401, 'GHS' => 172800];

    /**
     * Required keys: email. Pass is_permanent=true with a bvn for a static account.
     */
    public function create(array $data): array
    {
        $data['tx_ref'] ??= Transactions::generateTransactionReference((string) ($this->config['prefix'] ?? 'LARAVEL-'));

        return $this->client->post('virtual-account-numbers', $data);
    }

    /**
     * Create a single-use NGN or GHS account for one payment. It expires after
     * `expires` seconds (Flutterwave's default is one hour). Any other create()
     * field (firstname, lastname, phonenumber, narration, bank_code, tx_ref) can go in $options.
     *
     * @see https://developer.flutterwave.com/docs/ngn-virtual-accounts
     */
    public function dynamic(string $email, int|float|string $amount, array $options = []): array
    {
        $currency = strtoupper((string) ($options['currency'] ?? 'NGN'));

        if (! is_numeric($amount) || $amount <= 0) {
            throw new InvalidArgument('A dynamic virtual account needs an amount greater than zero.');
        }

        if (! isset(self::MAX_EXPIRY[$currency])) {
            throw new InvalidArgument("Virtual accounts support NGN and GHS, not {$currency}.");
        }

        if (isset($options['expires']) && ((int) $options['expires'] < 60 || (int) $options['expires'] > self::MAX_EXPIRY[$currency])) {
            throw new InvalidArgument("{$currency} virtual accounts can expire after 60 to ".self::MAX_EXPIRY[$currency].' seconds.');
        }

        return $this->create($this->filter([
            ...$options,
            'email' => $email,
            'amount' => $amount,
            'currency' => $currency,
            'expires' => isset($options['expires']) ? (string) (int) $options['expires'] : null,
            'is_permanent' => false,
        ]));
    }

    /**
     * Get a dynamic account ready to show a customer, e.g. as an Inertia prop.
     *
     * With a tx_ref, the account is cached until it expires, so reloading the
     * checkout page shows the same account number. Returns Flutterwave's account
     * fields plus currency, tx_ref, expires_at (ISO 8601) and expires_in (seconds left).
     */
    public function dynamicFor(string $email, int|float|string $amount, ?string $txRef, array $options = []): array
    {
        $key = $txRef === null ? null : 'flutterwave:virtual-account:'.$txRef;
        $cached = $key === null ? null : Cache::get($key);

        if (is_array($cached) && $cached['expires_at'] > time()) {
            return $this->present($cached);
        }

        $currency = strtoupper((string) ($options['currency'] ?? 'NGN'));
        $data = $this->dynamic($email, $amount, array_filter([...$options, 'tx_ref' => $txRef], static fn ($value) => $value !== null))['data'] ?? [];

        $created = [
            'data' => [...$data, 'currency' => $currency, 'tx_ref' => $txRef],
            'expires_at' => time() + $this->lifetime($data, (int) ($options['expires'] ?? 3600)),
        ];

        if ($key !== null) {
            Cache::put($key, $created, max(1, $created['expires_at'] - time()));
        }

        return $this->present($created);
    }

    public function bulk(array $data): array
    {
        return $this->client->post('bulk-virtual-account-numbers', $data);
    }

    public function find(string $orderRef): array
    {
        return $this->client->get("virtual-account-numbers/{$orderRef}");
    }

    public function findBulk(string $batchId): array
    {
        return $this->client->get("bulk-virtual-account-numbers/{$batchId}");
    }

    public function updateBvn(string $orderRef, string $bvn): array
    {
        return $this->client->put("virtual-account-numbers/{$orderRef}", ['bvn' => $bvn]);
    }

    public function delete(string $orderRef): array
    {
        return $this->client->post("virtual-account-numbers/{$orderRef}", ['status' => 'inactive']);
    }

    private function present(array $created): array
    {
        return [
            ...$created['data'],
            'expires_at' => date(DATE_ATOM, $created['expires_at']),
            'expires_in' => max(0, $created['expires_at'] - time()),
        ];
    }

    /**
     * Flutterwave returns local times without a timezone, so work out how long
     * the account lives from the gap between created_at and expiry_date.
     */
    private function lifetime(array $data, int $default): int
    {
        $created = strtotime((string) ($data['created_at'] ?? ''));
        $expiry = strtotime((string) ($data['expiry_date'] ?? ''));

        return $created !== false && $expiry !== false && $expiry > $created ? $expiry - $created : $default;
    }
}
