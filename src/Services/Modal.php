<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Exception;
use Flutterwave\Payments\Data\Api;
use Flutterwave\Payments\Exception\InvalidArgument;
use Flutterwave\Payments\Support\ApiClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * Builds inline checkout configs and hosted (standard) payment links.
 */
class Modal
{
    private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR;

    private array $config;

    private Api $api;

    private LoggerInterface $logger;

    public function __construct(Api $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
        $this->logger = Log::channel('flutterwave');
    }

    /**
     * Render Inline (JSON config for FlutterwaveCheckout) or Standard (hosted link).
     *
     * @throws Exception
     */
    public function render(array $data, string $type = 'inline'): string
    {
        if ($type !== 'inline') {
            return $this->standardRequest($data);
        }

        if (empty($this->config['public_key'])) {
            throw new InvalidArgument('The Flutterwave public key is missing. Add FLW_PUBLIC_KEY to your .env file.');
        }

        $data = $this->prepare($data);

        return json_encode([
            'public_key' => $this->config['public_key'],
            'payment_options' => $this->paymentOptions(),
            ...$data,
        ], self::JSON_FLAGS);
    }

    public static function displayInline(array $data): View
    {
        return view('flutterwave::modal', compact('data'));
    }

    /**
     * Fill in defaults so callers only need an amount and an email.
     */
    public function prepare(array $data): array
    {
        if (isset($data['email']) && ! isset($data['customer'])) {
            $data['customer'] = ['email' => $data['email']];
        }
        unset($data['email']);

        $data = [
            ...$data,
            'tx_ref' => $data['tx_ref'] ?? Transactions::generateTransactionReference((string) ($this->config['prefix'] ?? 'LARAVEL-')),
            'currency' => $data['currency'] ?? $this->config['currency'] ?? 'NGN',
            'redirect_url' => $data['redirect_url'] ?? $this->config['redirect_url'] ?? null,
            'customizations' => array_merge(array_filter([
                'title' => $this->config['title'] ?? null,
                'description' => $this->config['description'] ?? null,
                'logo' => $this->config['logo'] ?? null,
            ]), $data['customizations'] ?? []),
        ];

        $this->validateRequest($data);

        return $data;
    }

    private function paymentOptions(): string
    {
        $options = $this->config['payment_options'] ?? [];

        return is_array($options) ? implode(',', $options) : (string) $options;
    }

    private function validateRequest(array $request): void
    {
        if (! isset($request['amount']) || ! is_numeric($request['amount']) || $request['amount'] <= 0) {
            $this->logger->notice('Flutterwave Modal::Missing or invalid amount');
            throw new InvalidArgument('A positive "amount" is required to start a Flutterwave payment.');
        }

        if (empty($request['customer']['email'])) {
            $this->logger->notice('Flutterwave Modal::Missing customer email');
            throw new InvalidArgument('A customer email is required. Pass "email" or "customer" => ["email" => ...].');
        }
    }

    /**
     * @throws Exception
     */
    private function standardRequest(array $request): string
    {
        $request = $this->prepare($request);
        $request['payment_options'] ??= $this->paymentOptions();

        $this->logger->info('Flutterwave::Generating payment link for '.$request['tx_ref']);

        $response = ApiClient::fromConfig($this->config)->post($this->api::STANDARD_ENDPOINT, $request);

        return $response['data']['link'];
    }
}
