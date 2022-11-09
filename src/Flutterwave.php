<?php

declare(strict_types=1);

namespace Flutterwave\Payments;

use Exception;
use Flutterwave\Payments\Data\Api;
use Flutterwave\Payments\Helpers\Modal;

class Flutterwave
{
    /**
     * @var array
     */
    private array $config;
    private Modal $modal;
    private mixed $services;
    private Api $api;

    /**
     * Flutterwave constructor
     */
    public function __construct()
    {
        $this->config = [
            'public_key' => config('flutterwave.publicKey'),
            'secret_key' => config('flutterwave.secretKey'),
            'redirect_url' => config('flutterwave.redirectUrl'),
            'title' => config('flutterwave.title'),
            'description' => config('flutterwave.description'),
            'logo' => config('flutterwave.logo'),
            'country' => config('flutterwave.country'),
            'env' => config('flutterwave.env'),
            'secret_hash' => config('flutterwave.secretHash'),
            'encryption_key' => config('flutterwave.encryptionKey'),
            'business_name' => config('flutterwave.businessName'),
            'success_url' => config('flutterwave.successUrl'),
            'cancel_url' => config('flutterwave.cancelUrl'),
        ];

        $this->loadServices();

        $this->modal = new Modal($this->config);
    }

    /**
     * @throws Exception
     */
    public function initiate(array $data, string $type): string
    {
        if ($type === 'standard') {
            return $this->standard($data);
        }
        return $this->inline($data);
    }

    /**
     * @throws Exception
     */
    public function use(string $service): object
    {
        if (! isset($this->services[$service])) {
            throw new Exception('Service not found');
        }

        return new $this->services[$service]($this->api, $this->config);
    }

    /**
     * @throws Exception
     */
    public function verifyTransaction(string $transactionId): array
    {
//        return (new Transactions(new Data\Api(), $this->config['secret_key']))->verify($transactionId);
        return $this->use('transactions')->verify($transactionId);
    }

    private function loadServices(): void
    {
        $this->api = new Data\Api();
        $this->services = require_once __DIR__ . '/Data/Services.php';
    }

    /**
     * @param array $data
     *
     * @throws Exception
     */
    private function inline(array $data): string
    {
        return $this->modal->getData($data);
    }

    /**
     * @param array $data
     *
     * @throws Exception
     */
    private function standard(array $data): string
    {
        return $this->modal->getData($data, 'standard');
    }
}
