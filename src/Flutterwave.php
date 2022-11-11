<?php

declare(strict_types=1);

namespace Flutterwave\Payments;

use Exception;
use Flutterwave\Payments\Data\Api;
use Flutterwave\Payments\Helpers\Event;
use Flutterwave\Payments\Services\Modal;
use Illuminate\Support\Facades\Log;

final class Flutterwave
{
    use Event;
    /**
     * @var array|null
     */
    private ?array $config;
    private Modal $modal;
    private Api $api;
    private \Psr\Log\LoggerInterface $logger;

    /**
     * Flutterwave constructor
     */
    public function __construct()
    {
        $this->logger = Log::channel('flutterwave');
        $this->loadConfig();
        $this->api = new Data\Api();
    }

    /**
     * @throws Exception
     */
    public function render(string $type, array $data): string
    {
        // check if the service is enabled
        if (! $this->config['services']['modals']) {
            $this->logger->notice("Flutterwave::{$type} service is not enabled");
            return route('flutterwave.error', ['message' => "{$type} service is not enabled"]);
        }

        if ($type !== 'inline' && $type !== 'standard') {
            $this->logger->notice("Flutterwave::please specify a valid type for the render method. Valid types are 'inline' and 'standard'");
            return route('flutterwave.error', ['message' => "please specify a valid type for the render method. Valid types are 'inline' and 'standard'"]);
        }

        return $this->use('modals')->render($data, $type);
    }

    /**
     * @throws Exception
     */
    public function use(string $service): object
    {
        $services = $this->config['services'];
        if (! isset($services[$service])) {
            $this->logger->error("Flutterwave::{$service} service not found");
            throw new Exception('{$service} service not found');
        }

        return new $services[$service]($this->api, $this->config);
    }

    public function generateTransactionReference(): string
    {
        return $this->use('transactions')::generateTransactionReference($this->config['prefix']);
    }

    /**
     * @throws Exception
     */
    public function verifyTransaction(string $transactionId): array
    {
        return $this->use('transactions')->verify($transactionId);
    }

    /**
     * @throws Exception
     */
    public function verifyTransactionReference(string $transactionId): array
    {
        return $this->use('transactions')->verifyTransactionReference($transactionId);
    }

    private function loadConfig(): void
    {
        $this->config = [
            'public_key' => config('flutterwave.publicKey'),
            'secret_key' => config('flutterwave.secretKey'),
            'redirect_url' => config('flutterwave.redirectUrl'),
            'title' => config('flutterwave.title'),
            'description' => config('flutterwave.description'),
            'logo' => config('flutterwave.logo'),
            'country' => config('flutterwave.country'),
            'currency' => config('flutterwave.currency'),
            'payment_options' => config('flutterwave.paymentType'),
            'prefix' => config('flutterwave.transactionPrefix'),
            'env' => config('flutterwave.env'),
            'secret_hash' => config('flutterwave.secretHash'),
            'encryption_key' => config('flutterwave.encryptionKey'),
            'business_name' => config('flutterwave.businessName'),
            'success_url' => config('flutterwave.successUrl'),
            'cancel_url' => config('flutterwave.cancelUrl'),
            'services' => config('flutterwave.services'),
        ];
        # TODO: $this->config = config('flutterwave');
    }
}
