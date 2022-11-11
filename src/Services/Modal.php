<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Exception;
use Flutterwave\Payments\Data\Api;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use function json_encode;
use Psr\Log\LoggerInterface;

final class Modal
{
    private ?string $publicKey;
    private ?string $secretKey;
    private string $redirectUrl;
    private string $title;
    private string $description;
    private string $logo;
    private string $country;
    private string $secret_hash;
    private string $env;
    private string $encryption_key;
    private Api $api;
    private string $currency;
    private string $success_url;
    private string $cancel_url;
    private string $payment_options;
    private string $business_name;
    /**
     * @var mixed
     */
    private LoggerInterface $logger;

    /**
     * Modal constructor.
     *
     * @param array $config
     */
    public function __construct(Api $api, array $config)
    {
        $this->logger = Log::channel('flutterwave');
        $this->publicKey = $config['public_key'] ?? null;
        $this->secretKey = $config['secret_key'] ?? null;
        $this->redirectUrl = $config['redirect_url'];
        $this->title = $config['title'];
        $this->description = $config['description'];
        $this->logo = $config['logo'];
        $this->country = $config['country'];
        $this->currency = $config['currency'];
        $this->payment_options = implode(',', $config['payment_options']); //O(n)
        $this->env = $config['env'];
        $this->secret_hash = $config['secret_hash'];
        $this->encryption_key = $config['encryption_key'];
        $this->business_name = $config['business_name'];
        $this->success_url = $config['success_url'];
        $this->cancel_url = $config['cancel_url'];
        $this->api = $api;
    }

    /**
     * @param array $data
     *
     * @throws Exception
     */
    public function render(array $data, string $type = 'inline'): string
    {
        if ($type !== 'inline') {
            return $this->standardRequest($data);
        }

        $data = $this->addSettings($data);

        $validation = $this->validateRequest($data);

        if ($validation !== 'true') {
            return $validation;
        }

        return json_encode([
            'public_key' => $this->publicKey,
            'payment_options' => $this->payment_options,
            ...$data,
        ]);
    }

    private function addSettings(array $data): array
    {
        // check if currency is set
        if (! isset($data['currency'])) {
            $data['currency'] = $this->currency;
        }

        return [
            ...$data,
            'redirect_url' => $data['redirect_url'] ?? $this->redirectUrl,
            'customizations' => [
                'title' => $this->title,
                'description' => $this->description,
                'logo' => $this->logo,
            ],
        ];
    }

    private function validateRequest(array $request): string
    {
        $required = [
            'tx_ref',
            'amount',
            'currency',
            'redirect_url',
        ];

        foreach ($required as $key) {
            if (! isset($request[$key])) {
                $this->logger->notice("Flutterwave Modal::Missing required field {$key} [standard request]");
                return route('flutterwave.error', ['message' => "Missing required field {$key}"]);
            }
        }

        return 'true';
    }

    /**
     * @throws Exception
     */
    private function standardRequest(array $request): string
    {
        $specific_route = $this->api::STANDARD_ENDPOINT;
        $this->logger->info("Flutterwave::Generated Payment Link [{$specific_route}]");
        $request = $this->addSettings($request);

        $validation = $this->validateRequest($request);
        if ($validation !== 'true') {
            return $validation;
        }

        $response = Http::withToken($this->secretKey)->post("https://api.flutterwave.com/v3/{$specific_route}", $request);
        return $response->json()['data']['link'];
    }
}
